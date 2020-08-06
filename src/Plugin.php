<?php
declare(strict_types=1);

namespace CsvView;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Http\ServerRequest;

class Plugin extends BasePlugin
{
    /**
     * Plugin name.
     *
     * @var string
     */
    protected $name = 'CsvView';

    /**
     * Load routes or not
     *
     * @var bool
     */
    protected $routesEnabled = false;

    /**
     * Console middleware
     *
     * @var bool
     */
    protected $consoleEnabled = false;

    /**
     * @inheritDoc
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        /**
         * Add CsvView to View class map through RequestHandler, if available, on Controller initialisation
         *
         * @link https://book.cakephp.org/4/en/controllers/components/request-handling.html#using-custom-viewclasses
         */
        EventManager::instance()->on('Controller.initialize', function (EventInterface $event) {
            $controller = $event->getSubject();
            if ($controller->components()->has('RequestHandler')) {
                $controller->RequestHandler->setConfig('viewClassMap.csv', 'CsvView.Csv');
            }
        });

        /**
         * Add a request detector named "csv" to check whether the request was for a CSV,
         * either through accept header or file extension
         *
         * @link https://book.cakephp.org/4/en/controllers/request-response.html#checking-request-conditions
         */
        ServerRequest::addDetector(
            'csv',
            [
                'accept' => ['text/csv'],
                'param' => '_ext',
                'value' => 'csv',
            ]
        );
    }
}
