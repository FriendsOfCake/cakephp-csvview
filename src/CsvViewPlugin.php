<?php
declare(strict_types=1);

namespace CsvView;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\ServerRequest;

class CsvViewPlugin extends BasePlugin
{
    /**
     * Plugin name.
     *
     * @var string
     */
    protected ?string $name = 'CsvView';

    /**
     * Load routes or not
     *
     * @var bool
     */
    protected bool $routesEnabled = false;

    /**
     * Console middleware
     *
     * @var bool
     */
    protected bool $consoleEnabled = false;

    /**
     * @inheritDoc
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
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
