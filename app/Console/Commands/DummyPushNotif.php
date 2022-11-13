<?php

namespace App\Console\Commands;

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class DummyPushNotif extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message2:send {title} {--body=body} {--token=*} {--topic=} {--condition=} {--image=} {--data=}';

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
    }

    private function sendCustomerCashbackNotif()
    {
        $this->send(
            'Hi, Customer!',
            'You have received cashback',
        );
    }

    private function sendInstagramNotif()
    {
        $people = ['ridzie.pap', 'adi_dixie', 'agathadipa', 'syariri1106', 'itsfahmee', 'cahyaadithaa', 'tbxtb', 'jaxhen', 'aulia_gaa', 'taya1221', 'xjaa75', 'ndeera.ndir12', 'zaza.lembar', 'geeree0604', 'nadya_bila', 'morenomoren', 'syafnitha', 'cecann.dela', 'dessieanggare', 'mellymelaty', 'manieznoer', 'lilyand', 'normalpipel', 'mendiy.good', 'nicetiy', 'mallorain.here', 'itsthatguy', 'rediredi', 'firasyafir', 'ciciaanisa', 'real.anitarhma', 'anggorobastian.z', 'halimatus.sdq', 'yura.itsyura', 'zeee_', 'rifahquinta', 'salsadipraja', 'quartananda.agatha'];

        for ($i = 0; $i < count($people); $i++) {
            $this->send(
                "[justeatit] {$people[$i]}",
                'Mentioned you in their story',
            );
            if ($i == 0) {
                sleep(1.5);
            }
            if ($i == 1) {
                sleep(0.5);
            }
            if ($i == 2) {
                sleep(0.1);
            }
        }
    }

    private function getAccessToken()
    {
        // specify the path to your application credentials
        $authPath = storage_path('app/google/auth.test.json');
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

    private function send($title, $body)
    {
        $firebaseProjectId = env('FIREBASE_PROJECT_ID');
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
                ],
            ];

            $reqBody['message']['apns'] = [
                'payload' => [
                    'aps' => [
                        'mutable-content' => 1,
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
                $reqBody['message']['token'] = $registrationToken;

                $this->sendNotification($firebaseProjectId, $accessToken, $reqBody);
            }
            return Command::SUCCESS;
        }

        $this->sendNotification($firebaseProjectId, $accessToken, $reqBody);
        return Command::SUCCESS;
    }
}
