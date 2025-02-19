<?php

namespace App\Dto;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Sort",
    title: "Sort",
    description: "Sorting options for a query.",
    required: ["field", "direction"],
    properties: [
        new OA\Property(
            property: "field",
            description: "Field to sort by.",
            type: "string",
            example: "invoice_number"
        ),
        new OA\Property(
            property: "direction",
            description: "Sort direction.",
            type: "string",
            enum: ["asc", "desc"],
            example: "asc"
        )
    ]
)]
class Sort
{
    // This DTO is only for documentation purposes.
}
