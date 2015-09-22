<?php
App::uses('AppController', 'Controller');

/**
 * Articles Controller
 *
 * @property Article $Article
 * @property PaginatorComponent $Paginator
 */
class ArticlesController extends AppController
{

    /**
     * Components
     *
     * @var array
     */
    public $uses = array('Article', 'Gospel', 'Tweet');
    public $components = array('Paginator');
    public $layout = 'admin';
    public $thumbnailsDirName = 'img/ArticlesThumbanils';
    public $thumbnailsDirNameClient = 'ArticlesThumbanils';
    public $limit = 6;
    public $imageWeight = 40;
    public $imageHeight = 300;
    public $imageWide = 300;

    /**
     * @return string
     */
    public function beforeFilter()
    {
        parent::beforeFilter();
        $this->Auth->allow(array(
            'index_frontend',
            'index_frontend_church',
            'search_church_ajax',
            'search_ajax',
            'view_frontend'));
    }

    public function getThumbnailsDirNameClient()
    {
        return $this->thumbnailsDirNameClient;
    }

    /**
     * @param string $thumbnailsDirNameClient
     */
    public function setThumbnailsDirNameClient($thumbnailsDirNameClient)
    {
        $this->thumbnailsDirNameClient = $thumbnailsDirNameClient;
    }

    /**
     * @return string
     */
    public function getThumbnailsDirName()
    {
        return $this->thumbnailsDirName;
    }

    /**
     * @param string $thumbnailsDirName
     */
    public function setThumbnailsDirName($thumbnailsDirName)
    {
        $this->thumbnailsDirName = $thumbnailsDirName;
    }

    /**
     * index method
     *
     * @return void
     */
    public function beforeRender()
    {
        $this->set('menu', 'articles');
    }

    public function index()
    {
//        $this->Article->recursive = 0;
        $this->set('articles', $this->Article->find('all',
            array(
                'recursive' => 0
            )));
    }

    /**
     * view method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function view($id = null)
    {
        if (!$this->Article->exists($id)) {
            throw new NotFoundException(__('Invalid article'));
        }
        $options = array('conditions' => array('Article.' . $this->Article->primaryKey => $id));
        $this->set('article', $this->Article->find('first', $options));
    }

    /**
     * add method
     *
     * @return void
     */
    public function add()
    {
        if ($this->request->is('post')) {
            $usuario = $this->Auth->user()['User'];
            $this->Article->create();
            $article = array();
            $article['Article']['text'] = $this->request->data['Article']['text'];
            $article['Article']['title'] = $this->request->data['Article']['title'];
//            Para cuando la seguridad este hecha agregarle el usuario
            $article['Article']['user_id'] = $usuario['id'];
            $article['Article']['date_of_publication'] = date('Y-m-d', strtotime($this->request->data['Article']['date_of_publication']));;
            $article['Article']['category_id'] = $this->request->data['Article']['category_id'];
            $article['Article']['tags'] = $this->request->data['Article']['tags'];
            $article['Article']['expiration_date'] = date('Y-m-d', strtotime($this->request->data['Article']['expiration_date']));;
            if (isset($this->request->data['Article']['public']) && !empty($this->request->data['Article']['public'])) {
                $article['Article']['public'] = $this->request->data['Article']['public'];
            } else {
                $article['Article']['public'] = false;
            }
            $article['Article']['intro_text'] = $this->request->data['Article']['intro_text'];
            //Comprobando que la imagen tiene las dimensiones correctas y que se haya pasado
            $unique_name = $this->Util->getUniqueName($this->request->data['Article']['thumbnails_url']['name']);
            if (isset($this->request->data['Article']['thumbnails_url']) && !empty($this->request->data['Article']['thumbnails_url'])) {
                if ($this->Util->validate_image($this->request->data['Article']['thumbnails_url']['tmp_name'], $this->request->data['Article']['thumbnails_url']['type'], $this->imageWeight, $this->imageWide, $this->imageHeight)) {
//               Comprobando qu se haya copiado correctamente la imagen
                    if ($this->Util->copyUploadedFile($this->request->data['Article']['thumbnails_url']['tmp_name'],
                        $unique_name, $this->getThumbnailsDirName())
                    ) {
                        $article['Article']['thumbnails_url'] = $this->getThumbnailsDirNameClient() . '/' . $unique_name;
                    } else {
                        $this->Session->setFlash(__('Lo sentimos no se ha podido insertar la imagen ha habido un problema en el servidor. Comuniquese con el personal de administracion y comuniquele este error.'), 'flash_toastr', array('type' => 'e', 'title' => ''));
                        $this->redirect($this->referer());
                    }
                } else {
                    $this->Session->setFlash(__('Lo sentimos no se ha podido insertar el articulo porque la imagen no tiene las dimensiones correctas o a tratado de subir un archivo que no es una imagen.'), 'flash_toastr', array('type' => 'e', 'title' => ''));
                    $this->redirect($this->referer());
                }
            } else {
                $this->Session->setFlash(__('Lo sentimos pero no podemos insertar el articulo porque tiene que insertar una imagen.'), 'flash_toastr', array('type' => 'e', 'title' => ''));
                $this->redirect($this->referer());
            }
            if ($this->Article->save($article)) {
                $this->Session->setFlash(__('Se ha insertado correctamente el articulo.'), 'flash_toastr', array('type' => 's', 'title' => ''));
                return $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('Lo sentimos, no se ha podido guardar el articulo. Intentelo mas tarde'), 'flash_toastr', array('type' => 'e', 'title' => ''));
            }
        }
        $users = $this->Article->User->find('list');
        $categories = $this->Article->Category->find('list');
        $this->set(compact('users', 'categories'));
    }

    /**
     * edit method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */

    public function publicize()
    {
        if ($this->request->is('post')) {
            if (isset($this->request->data['Article']['select']) && !empty($this->request->data['Article']['select'])) {
                foreach ($this->request->data['Article']['select'] as $target) {
                    $this->Article->id = $target;
                    $this->Article->saveField('public', true);
                }
                $this->Session->setFlash(__('Los articulos seleccionados han sido publicados'), 'flash_toastr', array('type' => 's', 'title' => ''));
                return $this->redirect($this->referer());
            }
            $this->Session->setFlash(__('Por favor seleccione articulos para poder realizar la accion seleccionada.'), 'flash_toastr', array('type' => 'e', 'title' => ''));
            return $this->redirect($this->referer());
        }
        return $this->redirect($this->referer());
    }

    public function not_publicize()
    {
        if ($this->request->is('post')) {
            if (isset($this->request->data['Article']['select']) && !empty($this->request->data['Article']['select'])) {
                foreach ($this->request->data['Article']['select'] as $target) {
                    $this->Article->id = $target;
                    $this->Article->saveField('public', false);
                }
                $this->Session->setFlash(__('Los articulos seleccionados han sido despublicados'), 'flash_toastr', array('type' => 's', 'title' => ''));
                return $this->redirect($this->referer());
            }
            $this->Session->setFlash(__('Por favor seleccione articulos para poder realizar la accion seleccionada.'), 'flash_toastr', array('type' => 'e', 'title' => ''));
            return $this->redirect($this->referer());
        }
        return $this->redirect($this->referer());
    }

    public function edit($id = null)
    {
        if (!$this->Article->exists($id)) {
            throw new NotFoundException(__('Invalid article'));
        }
        if ($this->request->is(array('post', 'put'))) {
//            obteniendo los datos del articulo sin modificar
            $oldArticle = $this->Article->findById($id);
//            los datos del usuario que lo esta modificando
            $usuario = $this->Auth->user()['User'];
            $this->Article->create();
//            seteando los datos del articulo para modificarlos
            $article = array();
            if (!isset($this->request->data['Article']['text']) || empty($this->request->data['Article']['text'])) {
                $this->Session->setFlash(__('Lo sentimos no se ha podido insertar el articulo porque la imagen no tiene las dimensiones correctas o a tratado de subir un archivo que no es una imagen.'), 'flash_toastr', array('type' => 'e', 'title' => ''));
                return $this->redirect($this->referer());
            }
            $controlInsertedImg = false;
            $article['Article']['text'] = $this->request->data['Article']['text'];
            $article['Article']['title'] = $this->request->data['Article']['title'];
//            Para cuando la seguridad este hecha agregarle el usuario
            $article['Article']['user_id'] = $usuario['id'];
            $article['Article']['id'] = $id;
            $article['Article']['date_of_publication'] = date('Y-m-d', strtotime($this->request->data['Article']['date_of_publication']));;
            $article['Article']['category_id'] = $this->request->data['Article']['category_id'];
            $article['Article']['tags'] = $this->request->data['Article']['tags'];
            $article['Article']['expiration_date'] = date('Y-m-d', strtotime($this->request->data['Article']['expiration_date']));;
            if (isset($this->request->data['Article']['public']) && !empty($this->request->data['Article']['public'])) {
                $article['Article']['public'] = $this->request->data['Article']['public'];
            } else {
                $article['Article']['public'] = false;
            }
            $article['Article']['intro_text'] = $this->request->data['Article']['intro_text'];
            //Comprobando que la imagen tiene las dimensiones correctas y que se haya pasado
            $unique_name = $this->Util->getUniqueName($this->request->data['Article']['thumbnails_url']['name']);
            if (isset($this->request->data['Article']['thumbnails_url']['size']) && $this->request->data['Article']['thumbnails_url']['size'] > 0) {
                if ($this->Util->validate_image($this->request->data['Article']['thumbnails_url']['tmp_name'], $this->request->data['Article']['thumbnails_url']['type'], $this->imageWeight, $this->imageWide, $this->imageHeight)) {
//               Comprobando qu se haya copiado correctamente la imagen
                    if ($this->Util->copyUploadedFile($this->request->data['Article']['thumbnails_url']['tmp_name'],
                        $unique_name, $this->getThumbnailsDirName())
                    ) {
                        $article['Article']['thumbnails_url'] = $this->getThumbnailsDirNameClient() . '/' . $unique_name;
                        $controlInsertedImg = true;
                    } else {
                        $this->Session->setFlash(__('Lo sentimos no se ha podido insertar la imagen ha habido un problema en el servidor. Comuniquese con el personal de administracion y comuniquele este error.'), 'flash_toastr', array('type' => 'e', 'title' => ''));
                        $this->redirect($this->referer());
                    }
                } else {
                    $this->Session->setFlash(__('Lo sentimos no se ha podido insertar el articulo porque la imagen no tiene las dimensiones correctas o a tratado de subir un archivo que no es una imagen.'), 'flash_toastr', array('type' => 'e', 'title' => ''));
                    $this->redirect($this->referer());
                }
            }
            if ($this->Article->save($article)) {
                //                Eliminando la imagen vieja y agregando la nueva imagen
                if (file_exists('img/' . $oldArticle['Article']['thumbnails_url']) && $controlInsertedImg) {
                    unlink('img/' . $oldArticle['Article']['thumbnails_url']);
                }
                $this->Session->setFlash(__('El Articulo se ha modificado correctamente.'), 'flash_toastr', array('type' => 's', 'title' => ''));
                return $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('Lo sentimos, no se ha podido guardar el articulo. Intentelo mas tarde'), 'flash_toastr', array('type' => 'e', 'title' => ''));
            }
        } else {
            $options = array('conditions' => array('Article.' . $this->Article->primaryKey => $id));
            $this->request->data = $this->Article->find('first', $options);
        }
        $users = $this->Article->User->find('list');
        $categories = $this->Article->Category->find('list');
        $this->set(compact('users', 'categories'));
    }

    /**
     * delete method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */

    public function delete()
    {
        if ($this->request->is('post')) {
            $this->request->allowMethod('post', 'delete');
            if (!empty($this->request->data['Article']['select'])) {
                $this->Article->deleteAll(array('Article.id' => $this->request->data['Article']['select']), true, false);
                $this->Session->setFlash(__('Se Han eliminado todos los articulos seleccionados.'), 'flash_toastr', array('type' => 's', 'title' => ''));
                return $this->redirect($this->referer());
            } else {
                $this->Session->setFlash(__('Por favor seleccione los articulos que desea eliminar.'), 'flash_toastr', array('type' => 'e', 'title' => ''));
            }
        }
        return $this->redirect(array('action' => 'index'));
    }

    public function index_frontend()
    {
        $this->layout = 'frontend';
        $this->paginate = array(
            'limit' => $this->limit,
            'order' => array('Article.date_of_publication' => 'desc'),
        );
        $this->Article->recursive = 1;
        $categories = $this->Article->Category->find('list',array('conditions'=>array('Category.is_church'=>false)));
        $date = date('Y-m-d', strtotime("now"));
        $this->set('articles', $this->paginate(array(
            'Article.public' => true,
            'Article.date_of_publication <=' => $date,
            'Article.expiration_date >=' => $date,
            'Category.public' => true,
            'Category.is_church' => false
        )));
        $gospel = $this->Gospel->findByDate($date);
        $this->set('gospel', $gospel);

        $tweet = $this->Tweet->find('all',array('order'=>array('Tweet.date'=>'desc'),'limit'=>1));
        if(!empty($tweet)){
            $this->set('tweet', $tweet[0]);
        }else{
            $this->set('tweet', null);
        }
        $this->set('title', 'Mas recientes');
        $this->set('categories', $categories);
        $this->set('page', 2);
        $this->set('menu_frontend', 'articles');
        $this->set('page_title', 'Articulos');

    }

    public function search_ajax()
    {
        $this->layout = 'ajax';
        $categories = $this->Article->Category->find('list',array('conditions'=>array('Category.is_church'=>false)));
        $this->set('categories', $categories);
        $page = 1;
        $this->set('pagination', true);
        $this->set('page', 2);
        $date = date('Y-m-d', strtotime("now"));
        if (!empty($this->request->data['page'])) {
            $page = (int)$this->request->data['page'];
            $this->set('pagination', false);
            $this->set('page', $page);
        }
        $this->Article->recursive = 1;
        $this->paginate = array('limit' => $this->limit, 'page' => $page, 'order' => array(
            'Article.date_of_publication' => 'desc'
        ));
        $result = '';
//            Compruebo que la categoria a filtar no es nula o no hay que filtar nada
        if ((!isset($this->request->data['category']) || empty($this->request->data['category'])) &&
            (!isset($this->request->data['word']) || empty($this->request->data['word']))
        ) {
            $this->set('articles', $this->paginate(array(
                'Article.public' => true,
                'Article.date_of_publication <=' => $date,
                'Article.expiration_date >=' => $date,
                'Category.public' => true,
                'Category.is_church' => false
            )));

        } else {
            if ((isset($this->request->data['category']) && !empty($this->request->data['category'])) &&
                (isset($this->request->data['word']) && !empty($this->request->data['word']))
            ) {
                $word = $this->request->data['word'];
                $result = $this->paginate('Article', array(
                    'Article.category_id' => $this->request->data['category'],
                    'OR' => array('Article.text LIKE ' => '%' . $word . '%', 'Article.title LIKE ' => '%' . $word . '%'),
                    'Article.public' => true,
                    'Article.date_of_publication <=' => $date,
                    'Article.expiration_date >=' => $date,
                    'Category.public' => true,
                    'Category.is_church' => false
                ));
            } else {
                if (isset($this->request->data['category']) && !empty($this->request->data['category']) &&
                    (!isset($this->request->data['word']) || empty($this->request->data['word']))
                ) {
                    $this->set('title', $this->Article->Category->findById($this->request->data['category'])['Category']['name']);
                    $this->set('selectedCategory', $this->request->data['category']);
                    $result = $this->paginate('Article', array(
                        'Article.category_id' => $this->request->data['category'],
                        'Article.public' => true,
                        'Article.date_of_publication <=' => $date,
                        'Article.expiration_date >=' => $date,
                        'Category.public' => true,
                        'Category.is_church' => false
                    ));
                } else {
                    $word = $this->request->data['word'];
                    $result = $this->paginate('Article', array(
                        'OR' => array('Article.text LIKE ' => '%' . $word . '%', 'Article.title LIKE ' => '%' . $word . '%'),
                        'Article.public' => true,
                        'Article.date_of_publication <=' => $date,
                        'Article.expiration_date >=' => $date,
                        'Category.public' => true,
                        'Category.is_church' => false
                    ));
                }
            }
//                Si hay qye filtrar las categorias, los articulos que tengan esa categoria y si hay algunos los muestro sino
//                vuelvo atras y digo que no hay articulos de esa categoria
            if (!empty($result)) {
                $this->set('articles', $result);
            } else {
                $this->Session->setFlash(__('Lo sentimos no hemos encontrado articulos para la los criterios seleccionados.'), 'flash_toastr', array('type' => 'w', 'title' => ''));
            }
        }
    }

    public function index_frontend_church()
    {
        $this->layout = 'frontend';
        $this->paginate = array(
            'limit' => $this->limit,
            'order' => array('Article.date_of_publication' => 'desc'),
        );
        $this->Article->recursive = 1;
        $categories = $this->Article->Category->find('list',array('conditions'=>array('Category.is_church'=>true)));
        $date = date('Y-m-d', strtotime("now"));;
        $this->set('articles', $this->paginate(array(
            'Article.public' => true,
            'Article.date_of_publication <=' => $date,
            'Article.expiration_date >=' => $date,
            'Category.public' => true,
            'Category.is_church' => true
        )));
        $gospel = $this->Gospel->findByDate($date);
        $this->set('gospel', $gospel);

        $tweet = $this->Tweet->find('all',array('order'=>array('Tweet.date'=>'desc'),'limit'=>1));
        if(!empty($tweet)){
            $this->set('tweet', $tweet[0]);
        }else{
            $this->set('tweet', null);
        }
        $this->set('categories', $categories);
        $this->set('title', 'Mas recientes');
        $this->set('page', 2);
//        Para setear el menu de la navegacion
        $this->set('menu_frontend', 'articles_church');
        $this->set('page_title', 'Articulos');

    }

    public function search_church_ajax()
    {
        $this->layout = 'ajax';
        $categories = $this->Article->Category->find('list',array('conditions'=>array('Category.is_church'=>true)));
        $this->set('categories', $categories);
        $page = 1;
        $this->set('pagination', true);
        $this->set('page', 2);
        $date = date('Y-m-d', strtotime("now"));
        if (!empty($this->request->data['page'])) {
            $page = (int)$this->request->data['page'];
            $this->set('pagination', false);
            $this->set('page', $page);
        }
        $this->Article->recursive = 1;
        $this->paginate = array('limit' => $this->limit, 'page' => $page, 'order' => array(
            'Article.date_of_publication' => 'desc'
        ));
        $result = '';
//            Compruebo que la categoria a filtar no es nula o no hay que filtar nada
        if ((!isset($this->request->data['category']) || empty($this->request->data['category'])) &&
            (!isset($this->request->data['word']) || empty($this->request->data['word']))
        ) {
            $this->set('articles', $this->paginate(array(
                'Article.public' => true,
                'Article.date_of_publication <=' => $date,
                'Article.expiration_date >=' => $date,
                'Category.public' => true,
                'Category.is_church' => true
            )));

        } else {
            if ((isset($this->request->data['category']) && !empty($this->request->data['category'])) &&
                (isset($this->request->data['word']) && !empty($this->request->data['word']))
            ) {
                $word = $this->request->data['word'];
                $result = $this->paginate('Article', array(
                    'Article.category_id' => $this->request->data['category'],
                    'OR' => array('Article.text LIKE ' => '%' . $word . '%', 'Article.title LIKE ' => '%' . $word . '%'),
                    'Article.public' => true,
                    'Article.date_of_publication <=' => $date,
                    'Article.expiration_date >=' => $date,
                    'Category.public' => true,
                    'Category.is_church' => true
                ));
            } else {
                if (isset($this->request->data['category']) && !empty($this->request->data['category']) &&
                    (!isset($this->request->data['word']) || empty($this->request->data['word']))
                ) {
                    $this->set('title', $this->Article->Category->findById($this->request->data['category'])['Category']['name']);
                    $this->set('selectedCategory', $this->request->data['category']);
                    $result = $this->paginate('Article', array(
                        'Article.category_id' => $this->request->data['category'],
                        'Article.public' => true,
                        'Article.date_of_publication <=' => $date,
                        'Article.expiration_date >=' => $date,
                        'Category.public' => true,
                        'Category.is_church' => true
                    ));
                } else {
                    $word = $this->request->data['word'];
                    $result = $this->paginate('Article', array(
                        'OR' => array('Article.text LIKE ' => '%' . $word . '%', 'Article.title LIKE ' => '%' . $word . '%'),
                        'Article.public' => true,
                        'Article.date_of_publication <=' => $date,
                        'Article.expiration_date >=' => $date,
                        'Category.public' => true,
                        'Category.is_church' => true
                    ));
                }
            }
//                Si hay qye filtrar las categorias, los articulos que tengan esa categoria y si hay algunos los muestro sino
//                vuelvo atras y digo que no hay articulos de esa categoria
            if (!empty($result)) {
                $this->set('articles', $result);
            } else {
                $this->Session->setFlash(__('Lo sentimos no hemos encontrado articulos para la los criterios seleccionados.'), 'flash_toastr', array('type' => 'w', 'title' => ''));
            }
        }
    }

    public function view_frontend($id)
    {
        $this->layout = 'frontend';
        if (!$this->Article->exists($id)) {
            throw new NotFoundException(__('Invalid article'));
        }
        $options = array(
            'recursive'=>2,
            'conditions' => array('Article.' . $this->Article->primaryKey => $id),
            'contain'=>array(
                'Comment'=>array(
                    'conditions'=>array(
                        'Comment.visibility'=>true
                    ),
                   'User'
                ),
                'Category',
                'User'
            )
        );
        $article=$this->Article->find('first', $options);
//        incrementando el contador de vistas
        $this->Article->id=$article['Article']['id'];
        $this->Article->saveField('count_of_views',($article['Article']['count_of_views']+1),false);
        $articlesSameCategory=$this->Article->find('all',
            array(
                'conditions'=>array('Article.id !='=>$article['Article']['id'],'Article.category_id'=>$article['Category']['id']),
                'limit'=>6,
                'order'=>'Article.date_of_publication desc'
            ));

        $this->set('article', $article);
        $this->set('articlesSameCategory', $articlesSameCategory);
        $this->set('menu_frontend', 'articles');
        $this->set('page_title', $article['Article']['title']);
    }
}
