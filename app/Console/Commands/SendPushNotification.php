<?php

namespace App\Console\Commands;

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class SendPushNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message:send {registrationToken*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $firebaseProjectId = env('FIREBASE_PROJECT_ID');
        $registrationTokens = $this->argument('registrationToken');

        // specify the path to your application credentials
        $authPath = storage_path('app/google/auth.json');
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $authPath);

        // define the scopes for your API call
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        // get access token
        $credentials = ApplicationDefaultCredentials::getCredentials($scopes);
        $accessToken = $credentials->fetchAuthToken()['access_token'];

        // send notification
        foreach ($registrationTokens as $registrationToken) {
            $data = [
                'message' => [
                    'notification' => [
                        'title' => 'Breaking News',
                        'body' => 'New news story available.'
                    ],
                    'data' => [
                        'story_id' => 'story_12345'
                    ],
                    // 'condition' => "'test' in topics || 'testing' in topics",
                    // 'topic' => 'test',
                    'token' => $registrationToken
                ]
            ];
            $json = json_encode($data);
            $client = new Client();
            $client->post('https://fcm.googleapis.com/v1/projects/' . $firebaseProjectId . '/messages:send', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json'
                ],
                'body' => $json
            ]);
        }
        return Command::SUCCESS;
    }
}
