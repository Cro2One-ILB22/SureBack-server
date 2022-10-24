<?php

namespace App\Console\Commands;

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class CreateNotificationGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message:create-group {name} {registrationToken*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('name');
        $registrationTokens = $this->argument('registrationToken');
        $serverKey = env('FIREBASE_SERVER_KEY');
        $senderId = env('FIREBASE_SENDER_ID');

        $body = [
            'operation' => 'create',
            'notification_key_name' => $name,
            'registration_ids' => $registrationTokens
        ];
        $json = json_encode($body);
        $client = new Client();
        $response = null;

        try {
            $response = $client->post('https://fcm.googleapis.com/fcm/notification', [
                'headers' => [
                    'Authorization' => 'key=' . $serverKey,
                    'Content-Type' => 'application/json',
                    'project_id' => $senderId
                ],
                'body' => $json
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return COMMAND::FAILURE;
        }

        // catch error
        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $this->error('Error: ' . $response->getBody());
            return COMMAND::FAILURE;
        }

        $this->info($response->getBody()->getContents());
        return COMMAND::SUCCESS;
    }
}
