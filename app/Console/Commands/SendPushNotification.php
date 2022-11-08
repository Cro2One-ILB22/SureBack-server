<?php

namespace App\Console\Commands;

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPushNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message:send {title} {--body=body} {--token=*} {--topic=} {--condition=} {--image=} {--data=}';

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
        $title = $this->argument('title');
        $body = $this->option('body');
        $image = $this->option('image');
        $topic = $this->option('topic');
        $condition = $this->option('condition');
        $registrationTokens = $this->option('token');
        $data = $this->option('data');

        $accessToken = $this->getAccessToken();

        $reqBody = [
            'message' => [
                'notification' => [
                    'title' => $title,
                ],
            ]
        ];

        if ($data) {
            $reqBody['message']['data'] = json_decode($data);
        }

        if ($body) {
            $reqBody['message']['notification']['body'] = $body;
        }

        if ($image) {
            $reqBody['message']['android'] = [
                'notification' => [
                    'image' => $image,
                    'sound' => 'default',
                ],
            ];

            $reqBody['message']['apns'] = [
                'headers' => [
                    "apns-priority" => "10",
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                    ],
                ],
                'fcm_options' => [
                    'image' => $image,
                ],
            ];

            $reqBody['message']['webpush'] = [
                'headers' => [
                    'image' => $image,
                ],
            ];
        }

        if ($topic) {
            $reqBody['message']['topic'] = $topic;
        } elseif ($condition) {
            $reqBody['message']['condition'] = $condition;
        } else {
            foreach ($registrationTokens as $registrationToken) {
                try {
                    $reqBody['message']['token'] = $registrationToken;

                    $this->sendNotification($firebaseProjectId, $accessToken, $reqBody);
                } catch (\Exception $e) {
                    Log::error($e->getMessage());
                }
            }
            return Command::SUCCESS;
        }

        $this->sendNotification($firebaseProjectId, $accessToken, $reqBody);
        return Command::SUCCESS;
    }

    private function getAccessToken()
    {
        // specify the path to your application credentials
        $authPath = storage_path('app/google/auth.json');
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $authPath);

        // define the scopes for your API call
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        // get access token
        $credentials = ApplicationDefaultCredentials::getCredentials($scopes);
        $accessToken = $credentials->fetchAuthToken()['access_token'];

        return $accessToken;
    }

    private function sendNotification($firebaseProjectId, $accessToken, $reqBody)
    {
        $json = json_encode($reqBody);
        $client = new Client();
        $client->post('https://fcm.googleapis.com/v1/projects/' . $firebaseProjectId . '/messages:send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json'
            ],
            'body' => $json
        ]);
    }
}
