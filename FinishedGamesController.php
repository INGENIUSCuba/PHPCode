<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Collection\Collection;
use Cake\Event\Event;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\I18n\Time;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 */
class FinishedGamesController extends AppController
{
	//Inicializando los modelos necesarios para el controlador
    public function  initialize()
    {
        parent::initialize();
        $this->loadComponent('Util');
        $this->loadModel('BadgesUsers');
        $this->loadModel('Players');
        $this->loadModel('Users');
        $this->loadModel('Games');
        $this->loadModel('Configurations');
        $this->loadModel('Badges');
        $this->loadModel('FreeGames');

    }
	//Funcion que principal que va llamando a las funciones mas especificas para en total optener toda la informacion necesaria del fichero a importar
    public function run()
    {
	//Funcion que obtiene la informacion que esta en el fichero a exportar
        $filesContents = $this->getContentOfGamesFiles();
		//Funcion que obtiene del fichero la informacion y la asigna a cada uno de los jugadores
        $playersInfo = $this->getPlayersInfo($filesContents);
		//funcion que actualiza a la bd con la informacion de los jugadores del juego cargado
        $game = $this->updateDb($playersInfo);
		//Funcion que actualiza las estadisticas presentes en el juego jugado y que se guardo ya en la bd
        $this->updateStatistics($game);

        $this->Flash->flashToastr(__('Se ha cargado el juego correctamente.'), ['params' => [
            'type' => 's',
            'title' => ''
        ]]);
		//Redireccionando a la pagina desde donde se ejecuto el proceso
        return $this->redirect($this->referer());
    }


    private function updateDb($playersInfo)
    {
//Creando la entidad de juego para despues guardarla en la bd con todos los datos obtenidos en el fichero
        $game = $this->Games->newEntity(['start' => Time::now(), 'end' => Time::now()]);
		//Salvando los datos del juego
        $game = $this->Games->save($game);
		//Salvando cada uno de los datos de los juegadores que partisiparon en el juego que se esta procesando
        foreach ($playersInfo as $username => $value) {
            $user = $this->Users->findByUsername($username)->first();
            $player = $this->Players->newEntity();
            $player->user_id = $user->id;
            $player->game_id = $game->id;
            $player->team_ranking = $value['team_ranking'];
            $player->team = $value['team'];
            $player->score = $value['score'];
            $player->shootings = $value['shootings'];
            $player->hits = $value['hits'];
            $player->kills = $value['kills'];
            $player->reborn = $value['reborn'];
            $player->reload = $value['reload'];
            $player->cure = $value['cure'];
            $player->accuracy = $value['accuracy'];
            $player->deaths = $value['deaths'];
            $this->Players->save($player);
//                Actualizar la entidad de usuario con los datos oobtenidos de esta partida
            $user->total_score = $user->total_score + $player->score;
            $user->count_games = $user->count_games + 1;
            $user->accuracy = ($user->accuracy + $player->accuracy) / 2;
            $user->deaths = ($user->deaths + $player->deaths);
            $user->kills = ($user->kills + $player->kills);
            $this->Users->save($user);
        }
//        Agregando los datos de fuego amigo a la bd
        foreach ($playersInfo as $username => $value) {
            foreach ($value['Player'] as $againstPlayer => $value2) {
                if ($value['team'] == $playersInfo[$againstPlayer]['team']) {
                    $userToSave = $this->Users->findByUsername($againstPlayer)->select(['id'])->first();
                    $playerToSave = $this->Players->find()->where(['user_id' => $userToSave->id, 'game_id' => $game->id])->first();
                    $playerToSave->friends_hits = $playerToSave->friends_hits + $value2['hit'];
                    $playerToSave->friends_deaths = $playerToSave->friends_deaths + $value2['death'];
                    $this->Players->save($playerToSave);
                }
            }
        }
        return $this->Games->get($game->id, ['contain' => ['Players' => ['Users' => ['Badges']]]]);
    }


    private function updateStatistics($game)
    {
	//Cargando las configuraciones de la bd necesarias para el procesamiento
        $configurations = $this->Configurations->find()->where(['Configurations.id' => 1])->first();

//        usuarios que participaron en el juego
        $players = $game->players;

//        Actualizando las medallas
        $this->updateBadges($players);

//        metodo que calcula si algun jugador a ganado alguna partida gratis
        $this->freeGamesStatistics($game->id);
//oteniendo el resultado del juego o sea que equipo gano
        $gameResult = $this->gameResult($players);
//Metodo que calcula cuanta experiencia gana o pierde cada jugador
        $this->experiencePoints($players, $configurations, $gameResult);

    }
//Funcion que se encarga de obtener la cantidad de juegos sin costo que tiene cada uno de los usuarios si alguno cumple con las condiciones
    public function freeGamesStatistics($game_id)
    {
        $game = $this->Games->find()->contain(['Players' => ['Users']])->where(['id' => $game_id])->toArray();
        $players = $game[0]['players'];
        $playersCollection = new Collection($players);
        $first_place = $playersCollection->max(function ($player) {
            return $player->score;
        });
        $controlAgainstSameRanks = false;
        foreach ($players as $player) {
            $userFreeGame = $this->FreeGames->find()->where(['user_id' => $player['user_id'], 'date' => Time::now()])->first();
            if (empty($userFreeGame->id)) {
                $userFreeGame = $this->FreeGames->newEntity();
                $userFreeGame->date = Time::now();
                $userFreeGame->user_id = $player->user_id;
                $userFreeGame->count_of_games = 1;
                $userFreeGame->count_of_first_rank = 0;
                $userFreeGame->count_of_first = 0;
                $userFreeGame->enable = 1;
                $this->FreeGames->save($userFreeGame);
            } else {
                $userFreeGame->count_of_games = $userFreeGame->count_of_games + 1;
                $this->FreeGames->save($userFreeGame);
            }
            if ($player['user_id'] != $first_place->user_id && $player->user->rank_id >= 2 && $first_place->user->rank_id >= 2) {
                $controlAgainstSameRanks = true;
            }
//            Incrementandole al primer lugar el contador de primeros lugares
            if ($player['user_id'] == $first_place->user_id) {
                $userFreeGame = $this->FreeGames->findByUser_id($first_place->user_id)->first();
                $userFreeGame->count_of_first += 1;
                $this->FreeGames->save($userFreeGame);
            }
        }
        if ($controlAgainstSameRanks) {
            $first_place_freeGame = $this->FreeGames->findByUser_id($first_place->user_id)->first();
            $first_place_freeGame->count_of_first_rank += 1;
            $this->FreeGames->save($first_place_freeGame);
        }
        $this->updateFreeGames();
    }
//agregando a la bd los juegos gratis que se obtuvieron en el juego que se esta procesando
    private function updateFreeGames()
    {
        $freeGames = $this->FreeGames->find()->where(['date' => Time::now()])->all();
        foreach ($freeGames as $freeGame) {
            $user = $this->Users->get($freeGame->user_id);
//            Si tiene diez partidas jugadas se le dan dos partidas gratis
            if ($freeGame->count_of_games / 10 == 1) {
                $this->addFreeGame($freeGame->user_id, 2);
                $freeGame->count_of_games = 0;
                $this->FreeGames->save($freeGame);
            }
//            Si tiene 5 primeros lugares o mas
            if ($freeGame->count_of_first / 5 == 1) {
                $this->addFreeGame($freeGame->user_id, 2);
                $freeGame->count_of_first = 0;
                $this->FreeGames->save($freeGame);
            }
            if ($user->rank_id == 2 && $freeGame->enable) {
                $this->addFreeGame($freeGame->user_id, 1);
            } elseif ($user->rank_id == 3 && $freeGame->count_of_first_rank >= 2 && $freeGame->enable) {
                $this->addFreeGame($freeGame->user_id, 2);
                $freeGame->enable = false;
                $this->FreeGames->save($freeGame);
            } elseif ($user->rank_id == 4 && $freeGame->count_of_first_rank >= 3 && $freeGame->enable) {
                $this->addFreeGame($freeGame->user_id, 3);
                $freeGame->enable = false;
                $this->FreeGames->save($freeGame);
            } elseif ($user->rank_id == 5 && $freeGame->count_of_first_rank >= 5 && $freeGame->enable) {
                $this->addFreeGame($freeGame->user_id, 5);
                $freeGame->enable = false;
                $this->FreeGames->save($freeGame);
            }
        }
    }
//funcion que guarda en la bd una cantidad de juegos libres de costo para un usuario especifico
    private function addFreeGame($user_id, $countOfFreeGames)
    {
        $user = $this->Users->get($user_id);
        $user->free_games = $user->free_games + $countOfFreeGames;
        if ($this->Users->save($user)) {
            return true;
        } else {
            return false;
        }
    }
//Funcion que se ocupa de el manejo de la experiencia para todos los usuarios que juegaron en el juego que se esta procesando
    private function experiencePoints($players, $gameResult)
    {
        $experiencePointsForPlayers = [];
        $playersCollection = new Collection($players);

        //                Calculando la experiencia por los rangos
        $playersSortedByScore = $playersCollection->sortBy('score');
        $this->getExperiencePointsForRanks($playersSortedByScore, $experiencePointsForPlayers);
//       Contando la cantidad de jugadores que hay por equipo
        $countPlayersBlueTeam = 0;
        $countPlayersRedTeam = 0;
        foreach ($players as $playerForCount) {
            if ($playerForCount->team == 'red') {
                $countPlayersRedTeam += 1;
            } else {
                $countPlayersBlueTeam += 1;
            }
        }

        foreach ($players as $player) {
            $count_of_games_for_player = $this->Players->find()->where(['user_id' => $player->user_id])->count();
            if ($count_of_games_for_player > 4) {
//                Si gana el equipo
                if ($player->team == $gameResult) {
                    $experiencePointsForPlayers[$player->user_id] += 100;
                }
//                calculando los puntos que gana el jugador por puntos ganados en el juego
                $experiencePointsForPlayers[$player->user_id] += $this->getExperiencePointsForPointsInGame($player->score);
//                calculando los puntos que gana el jugador por efectividad en el juego
                $experiencePointsForPlayers[$player->user_id] += $this->getExperiencePointsForAccuracyInGame($player->score);
//                calculando los puntos que gana el jugador por kills en el juego
                $experiencePointsForPlayers[$player->user_id] += $this->getExperiencePointsForKillsInGame($player->score);
//                calculando los puntos que gana el jugador por muertes recibidas en el juego
                $experiencePointsForPlayers[$player->user_id] += $this->getExperiencePointsForDeathsInGame($player->score);
//                todo: Arreglar esto
//                calculando los puntos que gana el jugador por fuego amigo en el juego
                $experiencePointsForPlayers[$player->user_id] -= $this->getExperiencePointsForFriendsKillsInGame($player->friends_hits, $player->friends_deaths);


//                Si queda en los tres primeros lugares del equipo y en el equipo hay mas de dos jugadores
                $controlOfTeamPositions = false;
                if ($player->team == 'blue' && $countPlayersBlueTeam > 2) {
                    $controlOfTeamPositions = true;
                } elseif ($player->team == 'red' && $countPlayersRedTeam > 2) {
                    $controlOfTeamPositions = true;
                }
                if ($controlOfTeamPositions) {
                    if ($player->team_ranking == 1) {
                        $experiencePointsForPlayers[$player->user_id] += 100;
                    } elseif ($player->team_ranking == 2) {
                        $experiencePointsForPlayers[$player->user_id] += 50;
                    } elseif ($player->team_ranking == 3) {
                        $experiencePointsForPlayers[$player->user_id] += 30;
                    }
                }

            }
        }
//Calculando el promedio de la experiencia acumulada y agregandosela al usuario
        foreach ($experiencePointsForPlayers as $user_id => $cumul_experience) {
            $experience = $cumul_experience / 14;
            $user = $this->Users->findById($user_id)->first();
            $user->experience += $experience;
            $this->Users->save($user);
        }
    }

    private function getExperiencePointsForPointsInGame($points)
    {
        $obteinedPoints = 0;
        if ($points > 50 && $points <= 80) {
            $obteinedPoints = 20;
        } elseif ($points > 80 && $points <= 90) {
            $obteinedPoints = 30;
        } elseif ($points > 90 && $points <= 100) {
            $obteinedPoints = 60;
        } elseif ($points > 100 && $points <= 150) {
            $obteinedPoints = 100;
        } elseif ($points > 150 && $points <= 200) {
            $obteinedPoints = 150;
        } elseif ($points > 200 && $points <= 300) {
            $obteinedPoints = 200;
        } elseif ($points > 300) {
            if (((int)($points - 300) / 100) >= 1) {
                $obteinedPoints = 400 + (((int)$points / 100) * 100);
            } else {
                $obteinedPoints = 400;
            }
        }
        return $obteinedPoints;
    }

    private function getExperiencePointsForAccuracyInGame($accuracy)
    {
        $obteinedPoints = 0;
        if ($accuracy > 30 && $accuracy <= 40) {
            $obteinedPoints = 50;
        } elseif ($accuracy > 40 && $accuracy <= 50) {
            $obteinedPoints = 60;
        } elseif ($accuracy > 50 && $accuracy <= 60) {
            $obteinedPoints = 80;
        } elseif ($accuracy > 60 && $accuracy <= 70) {
            $obteinedPoints = 120;
        } elseif ($accuracy > 70 && $accuracy <= 80) {
            $obteinedPoints = 200;
        } elseif ($accuracy > 91 && $accuracy <= 100) {
            $obteinedPoints = 500;
        }
        return $obteinedPoints;
    }

    private function getExperiencePointsForKillsInGame($kills)
    {
        $obteinedPoints = 0;
        if ($kills > 60 && $kills <= 10) {
            $obteinedPoints = 40;
        } elseif ($kills > 10 && $kills <= 20) {
            $obteinedPoints = 60;
        } elseif ($kills > 20 && $kills <= 30) {
            $obteinedPoints = 80;
        } elseif ($kills > 30) {
            if (((int)($kills - 30) / 10) >= 1) {
                $obteinedPoints = 100 + (((int)$kills / 10) * 100);
            } else {
                $obteinedPoints = 100;
            }
        }
        return $obteinedPoints;
    }

    private function getExperiencePointsForDeathsInGame($deaths)
    {
        $obteinedPoints = 0;
        if ($deaths > 0 && $deaths <= 2) {
            $obteinedPoints = 200;
        } elseif ($deaths > 2 && $deaths <= 4) {
            $obteinedPoints = 100;
        } elseif ($deaths > 40 && $deaths <= 6) {
            $obteinedPoints = 50;
        } elseif ($deaths > 6 && $deaths <= 9) {
            $obteinedPoints = 30;
        } elseif ($deaths > 9) {
            $obteinedPoints = 10;
        }
        return $obteinedPoints;
    }

    private function getExperiencePointsForFriendsKillsInGame($friendsHits, $friendsKills)
    {
        return ($friendsHits * 50) + ($friendsKills * 100);
    }

//Los jugadores tienen que estar organizados por orden de rankin total
    private function getExperiencePointsForRanks($players, &$experiencePointsForPlayers)
    {
        $players = $players->toArray();
        $count = count($players);
        for ($i = 0; $i < $count; $i++) {
            $player = $players[$i];
            $count_of_games_for_player = $this->Players->find()->where(['user_id' => $player['user_id']])->count();
            if ($count_of_games_for_player > 4) {
                $experiencePointsForPlayers[$player['user_id']] = 0;
//            RECLUTA
                if ($players[$i]['user']['rank_id'] == 2) {
//                Cuando gana el jugador
                    for ($u = $i + 1; $u < $count; $u++) {
                        $rank = $players[$u]['User']['rank_id'];
                        if ($rank == 2) {
                            $experiencePointsForPlayers[$player['user_id']] += 10;
                        } elseif ($rank == 3) {
                            $experiencePointsForPlayers[$player['user_id']] += 20;
                        } elseif ($rank == 4) {
                            $experiencePointsForPlayers[$player['user_id']] += 40;
                        } elseif ($rank == 5) {
                            $experiencePointsForPlayers[$player['user_id']] += 50;
                        }
                    }
//                Cuando pierde el jugador
                    for ($u = 0; $u < $i; $u++) {
                        $rank = $players[$u]['User']['rank_id'];
                        if ($rank == 2) {
                            $experiencePointsForPlayers[$player['user_id']] -= 50;
                        } elseif ($rank == 3) {
                            $experiencePointsForPlayers[$player['user_id']] -= 40;
                        } elseif ($rank == 4) {
                            $experiencePointsForPlayers[$player['user_id']] -= 20;
                        } elseif ($rank == 5) {
                            $experiencePointsForPlayers[$player['user_id']] -= 10;
                        }
                    }
                }
//            EXPERTO
                if ($players[$i]['user']['rank_id'] == 3) {
//                Cuando gana el jugador
                    for ($u = $i + 1; $u < $count; $u++) {
                        $rank = $players[$u]['User']['rank_id'];
                        if ($rank == 2) {
                            $experiencePointsForPlayers[$player['user_id']] += 5;
                        } elseif ($rank == 3) {
                            $experiencePointsForPlayers[$player['user_id']] += 20;
                        } elseif ($rank == 4) {
                            $experiencePointsForPlayers[$player['user_id']] += 40;
                        } elseif ($rank == 5) {
                            $experiencePointsForPlayers[$player['user_id']] += 50;
                        }
                    }
//                Cuando pierde el jugador
                    for ($u = 0; $u < $i; $u++) {
                        $rank = $players[$u]['User']['rank_id'];
                        if ($rank == 2) {
                            $experiencePointsForPlayers[$player['user_id']] -= 50;
                        } elseif ($rank == 3) {
                            $experiencePointsForPlayers[$player['user_id']] -= 40;
                        } elseif ($rank == 4) {
                            $experiencePointsForPlayers[$player['user_id']] -= 20;
                        } elseif ($rank == 5) {
                            $experiencePointsForPlayers[$player['user_id']] -= 5;
                        }
                    }
                }
//            VETERANO
                if ($players[$i]['user']['rank_id'] == 4) {
//                Cuando gana el jugador
                    for ($u = $i + 1; $u < $count; $u++) {
                        $rank = $players[$u]['User']['rank_id'];
                        if ($rank == 2) {
                            $experiencePointsForPlayers[$player['user_id']] += 3;
                        } elseif ($rank == 3) {
                            $experiencePointsForPlayers[$player['user_id']] += 10;
                        } elseif ($rank == 4) {
                            $experiencePointsForPlayers[$player['user_id']] += 30;
                        } elseif ($rank == 5) {
                            $experiencePointsForPlayers[$player['user_id']] += 50;
                        }
                    }
//                Cuando pierde el jugador
                    for ($u = 0; $u < $i; $u++) {
                        $rank = $players[$u]['User']['rank_id'];
                        if ($rank == 2) {
                            $experiencePointsForPlayers[$player['user_id']] -= 50;
                        } elseif ($rank == 3) {
                            $experiencePointsForPlayers[$player['user_id']] -= 40;
                        } elseif ($rank == 4) {
                            $experiencePointsForPlayers[$player['user_id']] -= 30;
                        } elseif ($rank == 5) {
                            $experiencePointsForPlayers[$player['user_id']] -= 3;
                        }
                    }
                }
//            ELITE
                if ($players[$i]['user']['rank_id'] == 5) {
//                Cuando gana el jugador
                    for ($u = $i + 1; $u < $count; $u++) {
                        $rank = $players[$u]['User']['rank_id'];
                        if ($rank == 2) {
                            $experiencePointsForPlayers[$player['user_id']] += 1;
                        } elseif ($rank == 3) {
                            $experiencePointsForPlayers[$player['user_id']] += 3;
                        } elseif ($rank == 4) {
                            $experiencePointsForPlayers[$player['user_id']] += 5;
                        } elseif ($rank == 5) {
                            $experiencePointsForPlayers[$player['user_id']] += 10;
                        }
                    }
//                Cuando pierde el jugador
                    for ($u = 0; $u < $i; $u++) {
                        $rank = $players[$u]['User']['rank_id'];
                        if ($rank == 2) {
                            $experiencePointsForPlayers[$player['user_id']] -= 70;
                        } elseif ($rank == 3) {
                            $experiencePointsForPlayers[$player['user_id']] -= 50;
                        } elseif ($rank == 4) {
                            $experiencePointsForPlayers[$player['user_id']] -= 40;
                        } elseif ($rank == 5) {
                            $experiencePointsForPlayers[$player['user_id']] -= 10;
                        }
                    }
                }

            }
        }
    }

//Obteniendo el resultado del juego si gano el equipo rojo o el azul
    private function gameResult($players)
    {
        $bluePoints = 0;
        $redPoints = 0;
        foreach ($players as $player) {
            if ($player->team == 'blue') {
                $bluePoints += $player->score;
            } else {
                $redPoints += $player->score;
            }
        }
        if ($bluePoints > $redPoints) {
            return 'blue';
        } elseif ($redPoints > $bluePoints) {
            return 'red';
        } else {
            return 'e';
        }
    }

	//actualizando la tabla de medallas
    private function updateBadges($players)
    {
        $accuracy = false;
        $terminator = false;
        $survival = false;
        $point = false;
        $eliteControl = false;
        foreach ($players as $player) {
            if ($player->accuracy > 60 && $player->score > 50) {
                $this->addBadges($player->user_id, 1);
                $accuracy = true;
            }
            if ($player->kills > 30) {
                $this->addBadges($player->user_id, 2);
                $terminator = true;
            }
            if ($player->deaths <= 1 && $player->score > 50) {
                $this->addBadges($player->user_id, 3);
                $survival = true;
            }
            if ($player->score > 200) {
                $this->addBadges($player->user_id, 4);
                $point = true;
            }
            //              Medalla de Elite
            if ($accuracy && $terminator && $survival && $point) {
                $this->addBadges($player->user_id, 6);
                $this->addBadges($player->user_id, 5);
                $eliteControl = false;
            }
//            Medallas de honor
            if (!empty($player->user->badges) && !$eliteControl) {
                $badges = $player->user->badges;
                $accuracy = false;
                $terminator = false;
                $survival = false;
                $point = false;
                foreach ($badges as $badge) {
                    if ($badge->id == 1) {
                        $accuracy = true;
                    }
                    if ($badge->id == 2) {
                        $terminator = true;
                    }
                    if ($badge->id == 3) {
                        $survival = true;
                    }
                    if ($badge->id == 4) {
                        $point = true;
                    }
                }
                if ($accuracy && $terminator && $survival && $point) {
                    $this->addBadges($player->user_id, 5);
                }
            }
        }
    }

	//agregadole una medalla a un usuario
    private function addBadges($user_id, $badge_id)
    {
        $badge = $this->Badges->get($badge_id);
        $pointsToAdd = $badge->points;
        $badgeUser = $this->BadgesUsers->newEntity(['badge_id' => $badge_id, 'user_id' => $user_id]);
        if ($this->BadgesUsers->save($badgeUser)) {
//                Agregandole los puntos restados al saco y dandoselo al usurio
            $user = $this->Users->get($user_id);
            $user->total_score = $user->total_score + $pointsToAdd;
            $this->Users->save($user);
        }
    }
//Obteniendo la informacion de los jugadores
    private function getPlayersInfo($filesContents)
    {
        $playersInfo = '';
        $last_player = '';
        foreach ($filesContents as $info) {
            $subInfo = explode(',', $info);
            if ($subInfo[0] == 'Player') {
                $last_player = $subInfo[3];
                $playersInfo[$subInfo[3]] = array(
                    'team_ranking' => $subInfo[1],
                    'team' => $subInfo[2],
                    'username' => $subInfo[3],
                    'score' => $subInfo[4],
                    'shootings' => $subInfo[5],
                    'hits' => $subInfo[6],
                    'kills' => $subInfo[7],
                    'reborn' => $subInfo[8],
                    'reload' => $subInfo[9],
                    'cure' => $subInfo[10],
                    'accuracy' => $subInfo[11]
                );
            } else {
                $playersInfo[$last_player]['Player'][$subInfo[1]] = array(
//                    'username' => $subInfo[1],
                    'hit' => $subInfo[2],
                    'death' => $subInfo[3]
                );
            }
        }
        foreach ($playersInfo as $key => &$value) {
            $deathCount = 0;
            foreach ($value['Player'] as $player_player) {
                $deathCount = $deathCount + $player_player['death'];
            }
            $value['deaths'] = $deathCount;
        }
        return $playersInfo;
    }
//Obtenieniendo el ultimo archivo en la url que aparece en el archivo de configuracion
    private function getLastFile()
    {
        $configurations_url_file = $this->Configurations->find()->where(['Configurations.id' => 1])->first();
        $folder = new Folder($configurations_url_file->url_game_file, false);
        $files = $folder->find('.*\.txt', true);
        $lastFile = null;
        $lasFileTime = 0;
        foreach ($files as $url_file) {
            $file = new File($folder->path . DS . $url_file, false);
            $fileTime = $file->lastChange();
            if ($fileTime > $lasFileTime) {
                $lasFileTime = $fileTime;
                $lastFile = $file;
            }
        }
        return $lastFile;
    }
//Obteniendo en bruto el contenido en memoria del archivo a procesar
    private function getContentOfGamesFiles()
    {
        $file = $this->getLastFile();
        $originalFileContent = $file->read($file->size());
        $content_for_lines = preg_split("/[\r\n]+/", $originalFileContent, null, PREG_SPLIT_NO_EMPTY);
        return array_slice($content_for_lines, 2);;
    }
}
