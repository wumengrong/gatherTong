<?php

namespace Laravel\Passport\Bridge;

use Laravel\Passport\ClientRepository as ClientModelRepository;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

class ClientRepository implements ClientRepositoryInterface
{
    /**
     * The client model repository.
     *
     * @var \Laravel\Passport\ClientRepository
     */
    protected $clients;

    /**
     * Create a new repository instance.
     *
     * @param  \Laravel\Passport\ClientRepository  $clients
     * @return void
     */
    public function __construct(ClientModelRepository $clients)
    {
        $this->clients = $clients;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientEntity($clientIdentifier, $grantType,
                                    $clientSecret = null, $mustValidateSecret = true)
    {
        // First, we will verify that the client exists and is authorized to create personal
        // access tokens. Generally personal access tokens are only generated by the user
        // from the main interface. We'll only let certain clients generate the tokens.
        $record = $this->clients->findActive($clientIdentifier);

        if (! $record || ! $this->handlesGrant($record, $grantType)) {
            return;
        }

        // Once we have an existing client record we will create this actual client instance
        // and verify the secret if necessary. If the secret is valid we will be ready to
        // return this client instance back out to the consuming methods and finish up.
        $client = new Client(
            $clientIdentifier, $record->name, $record->redirect
        );

        if ($mustValidateSecret &&
            ! hash_equals($record->secret, (string) $clientSecret)) {
            return;
        }

        return $client;
    }

    /**
     * Determine if the given client can handle the given grant type.
     *
     * @param  \Laravel\Passport\Client  $record
     * @param  string  $grantType
     * @return bool
     */
    protected function handlesGrant($record, $grantType)
    {
        switch ($grantType) {
            case 'authorization_code':
                return ! $record->firstParty();
            case 'personal_access':
                return $record->personal_access_client;
            case 'password':
                return $record->password_client;
            default:
                return true;
        }
    }
}
