<?php
use Cake\Event\EventManager;
use Cake\Event\Event;
use Cake\Http\ServerRequest;

EventManager::instance()->on('Controller.initialize', function (Event $event) {
    $controller = $event->getSubject();
    if ($controller->components()->has('RequestHandler')) {
        $controller->RequestHandler->config('viewClassMap.csv', 'CsvView.Csv');
    }
});

ServerRequest::addDetector(
    'csv',
    [
        'accept' => ['text/csv'],
        'param' => '_ext',
        'value' => 'csv',
    ]
);
