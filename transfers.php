#!/usr/bin/env php
<?php declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use League\Csv\Reader;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\HttpClient\HttpClient;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

(new SingleCommandApplication())
    ->setName('LunchMoney Transfer Import Tool')
    ->setVersion('1.0.0')
    ->addArgument('file', InputArgument::OPTIONAL, 'The file to process')
    ->addOption('currency', null, InputOption::VALUE_OPTIONAL, 'The currency to use')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        
        $client = HttpClient::createForBaseUri('https://dev.lunchmoney.app', [
            'auth_bearer' => $_ENV['access_token'],
        ]);

        $assets = $client->request('GET', 'https://dev.lunchmoney.app/v1/assets')->toArray()['assets'];
        print_r($assets);

        $categories = $client->request('GET', 'https://dev.lunchmoney.app/v1/categories')->toArray()['categories'];
        print_r($categories);
        die();

        $csv = Reader::createFromPath($input->getArgument('file'));
        $csv->setHeaderOffset(0);

        foreach($csv->getRecords() as $record) {
            if (empty($record['From']) || empty($record['To'])) {
                continue;
            }

            $fromAssetId = null;
            $toAssetId = null;

            foreach ($assets as $asset) {
                if ($asset['name'] === $record['From']) {
                    $fromAssetId = $asset['id'];
                } elseif ($asset['name'] === $record['To']) {
                    $toAssetId = $asset['id'];
                }
            }

            if ($fromAssetId !== null && $toAssetId !== null) {
                $amount = floatval(str_replace('$', '', $record['Expense']));

                $creditTransaction = [
                    'date' => $record['Date'],
                    'amount' => $amount * -1,
                    'category_id' => 255201,
                    'payee' => 'Transfer',
                    'currency' => $input->getOption('currency'),
                    'asset_id' => $fromAssetId,
                    'notes' => $record['Notes']
                ];

                $debitTransaction = [
                    'date' => $record['Date'],
                    'amount' => $amount,
                    'category_id' => 255201,
                    'payee' => 'Transfer',
                    'currency' => $input->getOption('currency'),
                    'asset_id' => $toAssetId,
                    'notes' => $record['Notes']
                ];

                var_dump($creditTransaction);die;

                $response = $client->request('POST', 'https://dev.lunchmoney.app/v1/transactions', [
                    'json' => [
                        'transactions' => [
                            $creditTransaction,
                            $debitTransaction,
                        ]
                    ]
                ]);

                var_dump($response->toArray());
            }

            die;
        }
    })
    ->run();
