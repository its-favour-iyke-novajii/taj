<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'crypto'], function () use ($router) {
    $router->post('/decrypt', 'Crypto@decrypt');
    $router->get('/hello', function () {
        return 'In Crypto';
    });
});

$router->group(['prefix' => 'ledger'], function () use ($router) {
    $router->post('/create', 'GeneralLedger@create');
    $router->get('/hello', function () {
        return 'Hello GL';
    });
});


$router->group(['prefix' => 'db'], function () use ($router) {
    $router->get('/download', 'OracleQueryDbController@download');
    
    $router->post('/run-download', 'DownloaderController@initiateDownload'); 
    
    $router->get('/hello', function () {
        return 'Hello GL';
    });
});


// Fetch from bkheve
$router->get('/bkheve', "ApiController@transactions");

$router->get('/filestr', "FileStrController");

//Fetch from Oracle
$router->post('/query', "OracleQueryDbController@query");

//Fetchh from Postgres
$router->post('/query-postgres', "PostgresQueryDbController@query");

//Update Postgres Postgres
$router->post('/update-postgres', "PostgresQueryDbController@update");


//Download from Postgres
$router->post('/download-pg', "DownloadPgController@download");


//Insert Postgres
$router->post('/insert-aml-report', "InsertPgController@amlInsert");

//stage data --Postgresql to oracle (ctr_transactions)
$router->post('/stagedata', "StagingController@migrateData");

//Background Download
//$router->post('/bg-download', 'DownloadController@initiateDownload');

$router->post('/bg-download', 'DownloadController@initiateDownload');








$router->group(['prefix' => 'db'], function () use ($router) {
    $router->post('/download', 'OracleQueryDbController@download');
    $router->get('/hello', function () {
        return 'Hello GL';
    });
});
