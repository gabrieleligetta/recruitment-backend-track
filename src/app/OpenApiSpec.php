<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "This is the API documentation for my Invoices application.",
    title: "My Laravel API"
)]
#[OA\Server(
    url: "http://localhost/api",
    description: "Local API Server"
)]
class OpenApiSpec
{
    // This class can be empty. Its sole purpose is to hold global OpenAPI attributes.
}
