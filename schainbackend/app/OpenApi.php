<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Schains ERP API',
    description: 'Schains ERP Laravel REST API Documentation'
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000',
    description: 'Local Laravel Development Server'
)]
class OpenApi
{
}