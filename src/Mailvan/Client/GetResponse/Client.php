<?php

namespace Mailvan\Client\GetResponse;

use Guzzle\Common\Collection;
use Guzzle\Service\Description\ServiceDescription;
use Mailvan\Core\Client as BaseClient;
use Mailvan\Core\MailvanException;
use Mailvan\Core\Model\SubscriptionList;
use Mailvan\Core\Model\SubscriptionListInterface;
use Mailvan\Core\Model\UserInterface;

class Client extends BaseClient
{
    public static function factory($config = array())
    {
        $required = array('base_url', 'api_key');

        $config = Collection::fromConfig($config, array(), $required);

        $client = new self($config->get('base_url'), $config);

        $client->setDescription(ServiceDescription::factory(dirname(__FILE__) . '/operations.json'));

        return $client;
    }

    /**
     * Merge API key into params array. Some implementations require to do this.
     *
     * @param $params
     * @return mixed
     */
    protected function mergeApiKey($params)
    {
        return $params;
    }

    /**
     * Check if server returned response containing error message.
     * This method must return true if servers does return error.
     *
     * @param $response
     * @return mixed
     */
    protected function hasError($response)
    {
        return isset($response['error']);
    }

    /**
     * Raises Exception from response data
     *
     * @param $response
     * @return MailvanException
     */
    protected function raiseError($response)
    {
        return new MailvanException($response['error']['message'], $response['error']['code']);
    }

    /**
     * Subscribes given user to given SubscriptionList. Returns true if operation is successful
     *
     * @param UserInterface $user
     * @param SubscriptionListInterface $list
     * @return boolean
     */
    public function subscribe(UserInterface $user, SubscriptionListInterface $list)
    {
        $params = array(
            'campaign' => $list->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
        );

        return $this->doExecuteCommand('addContact', $params, function () {
            return true;
        });
    }

    /**
     * Unsubscribes given user from given SubscriptionList.
     *
     * @param UserInterface $user
     * @param SubscriptionListInterface $list
     * @return boolean
     */
    public function unsubscribe(UserInterface $user, SubscriptionListInterface $list)
    {
        $contact_id = $this->findContactId($user, $list);

        return $this->doExecuteCommand('deleteContact', array('contact' => $contact_id), function () {
            return true;
        });
    }

    /**
     * Moves user from one list to another. In some implementation can create several http queries.
     *
     * @param UserInterface $user
     * @param SubscriptionListInterface $from
     * @param SubscriptionListInterface $to
     * @return boolean
     */
    public function move(UserInterface $user, SubscriptionListInterface $from, SubscriptionListInterface $to)
    {
        $params = array(
            'contact' => $this->findContactId($user, $from),
            'campaign' => $this->findListId($to),
        );

        return $this->doExecuteCommand('moveContact', $params, function () {
            return true;
        });
    }

    /**
     * Returns list of subscription lists created by owner.
     *
     * @return array
     */
    public function getLists()
    {
        return $this->doExecuteCommand('getCampaigns', array(), function ($response) {
            return array_map(
                function ($item) {
                    return new SubscriptionList($item['name']);
                },
                $response
            );
        });
    }

    protected function doExecuteCommand($command, $params, \Closure $responseParser)
    {
        return parent::doExecuteCommand($command, $this->buildRpcCall($params), $responseParser);
    }

    private function buildRpcCall($params = null)
    {
        $arguments = array(
            $this->getConfig('api_key'),
        );

        if (!is_null($params)) {
            $arguments[] = (array)$params;
        }

        return array(
            'id' => rand(100, 120),
            'params' => $arguments,
        );
    }

    private function findContactId(UserInterface $user, SubscriptionListInterface $list = null)
    {
        $params = array('email' => array('EQUALS' => $user->getEmail()));
        if (!is_null($list)) {
            $params['campaigns'] = array($list->getId());
        }

        return $this->doExecuteCommand('getContacts', $params,
            function ($response) {
                $contact_ids = array_keys($response);

                return reset($contact_ids);
            }
        );
    }

    private function findListId(SubscriptionListInterface $list)
    {
        return $this->doExecuteCommand('getCampaigns',
            array('name' => array('EQUALS' => $list->getId())),
            function ($response) {
                $list_ids = array_keys($response);

                return reset($list_ids);
            }
        );
    }
}
