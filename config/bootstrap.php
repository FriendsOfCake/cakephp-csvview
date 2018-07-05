<?php
use Cake\Event\EventManager;
use Cake\Event\Event;
use Cake\Http\ServerRequest as Request;

EventManager::instance()->on('Controller.initialize', function (Event $event) {
    $controller = $event->getSubject();
    if ($controller->components()->has('RequestHandler')) {
        $controller->RequestHandler->config('viewClassMap.csv', 'CsvView.Csv');
    }
});

Request::addDetector('csv', [
    'accept' => ['text/csv'],
    'param' => '_ext',
    'value' => 'csv',
]);
