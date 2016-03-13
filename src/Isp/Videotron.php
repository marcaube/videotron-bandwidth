<?php

namespace Ob\Bandwidth\Isp;

use Goutte\Client;
use Ob\Bandwidth\BandwidthUsage;
use Ob\Bandwidth\InternetServiceProvider;
use Ob\Bandwidth\InvalidCredentials;

final class Videotron implements InternetServiceProvider
{
    const URL_LOGIN     = 'https://www.videotron.com/client/residentiel/Espace-client';
    const URL_BANDWIDTH = 'https://www.videotron.com/client/residentiel/secur/CIUserSecurise.do?locale=en';
    const URL_LOGOUT    = 'https://www.videotron.com/client/user-management/residentiel/secur/Logout.do?dispatch=logout';

    // Captures "March 3" and "April 2" in "Usage from  March 3 to April 2, 2016"
    const REGEX_PERIOD = '/Usage from\s+(.*?) to\s+(.+),/';

    // Captures 24.4 and 130 from "24.4 / 130 GB"
    const REGEX_USAGE = '/(\d+(?:.\d+)) \/ (\d+(?:.\d+))/';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @param Client $client
     * @param string $username
     * @param string $password
     */
    public function __construct(Client $client, $username, $password)
    {
        $this->client   = $client;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @throws InvalidCredentials
     */
    public function login()
    {
        $crawler = $this->client->request('GET', self::URL_LOGIN);

        $form = $crawler->selectButton('Connexion')->form();

        $crawler = $this->client->submit($form, [
            'codeUtil'   => $this->username,
            'motDePasse' => $this->password,
        ]);

        $crawler->filter('.error, .msg-error')->each(function ($node) {
            $message = trim($node->text());

            throw new InvalidCredentials($message);
        });
    }

    public function logout()
    {
        $this->client->request('GET', self::URL_LOGOUT);
    }

    public function getBandwidthUsage()
    {
        $crawler = $this->client->request('GET', self::URL_BANDWIDTH);

        preg_match(
            self::REGEX_PERIOD,
            $crawler->filter('#titre_consommation h3')->first()->text(),
            $period
        );

        preg_match(
            self::REGEX_USAGE,
            $crawler->filter('.quantities')->first()->text(),
            $usage
        );

        return new BandwidthUsage(
            new \DateTimeImmutable($period[1]),
            new \DateTimeImmutable($period[2]),
            (float) $usage[2],
            (float) $usage[1]
        );
    }
}
