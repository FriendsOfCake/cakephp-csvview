<?php
use Cake\Event\EventManager;
use Cake\Event\Event;
use Cake\Http\ServerRequest;

/**
 * Add CsvView to View class map through RequestHandler, if available, on Controller initialisation
 *
 * @link https://book.cakephp.org/3.0/en/controllers/components/request-handling.html#using-custom-viewclasses
 */
EventManager::instance()->on('Controller.initialize', function (Event $event) {
    $controller = $event->getSubject();
    if ($controller->components()->has('RequestHandler')) {
        $controller->RequestHandler->setConfig('viewClassMap.csv', 'CsvView.Csv');
    }
});

/**
 * Add a request detector named "csv" to check whether the request was for a CSV,
 * either through accept header or file extension
 *
 * @link https://book.cakephp.org/3.0/en/controllers/request-response.html#checking-request-conditions
 */
ServerRequest::addDetector(
    'csv',
    [
        'accept' => ['text/csv'],
        'param' => '_ext',
        'value' => 'csv',
    ]
);
