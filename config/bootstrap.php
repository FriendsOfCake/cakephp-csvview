<?php
use Cake\Event\EventManager;
use Cake\Network\Request;

EventManager::instance()->on('Controller.initialize', function (Cake\Event\Event $event) {
  $controller = $event->subject();
  if ($controller->components()->has('RequestHandler')) {
    $controller->RequestHandler->config('viewClassMap.csv', 'CsvView.Csv');
  }
});

Request::addDetector('csv', ['accept' => ['text/csv'], 'param' => '_ext', 'value' => 'csv']);
