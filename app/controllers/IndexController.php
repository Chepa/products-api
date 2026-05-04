<?php

declare(strict_types=1);

namespace App\Controllers;

use Phalcon\Http\Response;

class IndexController extends ControllerBase
{
    public function indexAction(): Response
    {
        $panelJs = BASE_PATH . '/public/build/panel.js';
        $this->view->setVar('assetsVer', is_file($panelJs) ? (string) filemtime($panelJs) : '0');
        $this->view->setVar('apiBase', getenv('APP_API_BASE') ?: '/api/v1');
        $this->view->pick('index');
        $this->view->start();
        $this->view->render('index', 'index');
        $this->view->finish();

        return $this->response->setContent($this->view->getContent());
    }

    public function swaggerAction(): Response
    {
        return $this->response->redirect('/swagger.html', true, 302);
    }
}
