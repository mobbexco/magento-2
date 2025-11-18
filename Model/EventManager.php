<?php

namespace Mobbex\Webpay\Model;

class EventManager
{
    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Magento\Framework\Event\ConfigInterface */
    public $eventConfig;

    /** @var \Magento\Framework\Event\ObserverFactory */
    public $observerFactory;

    public function __construct(
        \Mobbex\Webpay\Helper\Logger $logger,
        \Magento\Framework\Event\ConfigInterface $eventConfig,
        \Magento\Framework\Event\ObserverFactory $observerFactory
    ) {
        $this->logger          = $logger;
        $this->eventConfig     = $eventConfig;
        $this->observerFactory = $observerFactory;
    }

    /**
     * Dispatch an event and retrieve the response.
     * 
     * @param string $name The event name (in camel case).
     * @param bool $filter Filter first arg in each execution.
     * @param mixed ...$args Arguments to pass.
     * 
     * @return mixed Last execution response or value filtered. Null on exceptions.
     */
    public function dispatch($name, $filter = false, ...$args)
    {
        try {
            // Use snake case to search event
            $eventName = ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $name)), '_');

            // Get registered observers and first arg to return as default
            $observers = $this->eventConfig->getObservers($eventName) ?: [];
            $value     = $filter ? reset($args) : false;

            $this->logger->log('debug', 'Dispatch event init', [$name, $filter, gettype($value), count($observers)]);

            foreach ($observers as $observerData) {
                // Instance observer
                $instanceMethod = !empty($observerData['shared']) ? 'get' : 'create';
                $observer       = $this->observerFactory->$instanceMethod($observerData['instance']);

                // Get method to execute
                $method = [$observer, $name];

                // Only execute if is callable
                if (!empty($observerData['disabled']) || !is_callable($method))
                    continue;

                $value = call_user_func_array($method, $args);
                $this->logger->log('debug', 'Dispatch event result', [$observerData['instance'], $name, $value]);

                if ($filter)
                    $args[0] = $value;
            }

            return $value;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Dispatch event error: ' . $e->getMessage());
        }
    }
}