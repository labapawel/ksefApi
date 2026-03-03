<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Support;

class XmlInvoiceParser
{
    public function extractMetadata(string $xmlContent): array
    {
        // TODO: parse XML and map business metadata.
        return [
            'invoice_number' => null,
            'invoice_date' => null,
            'seller_nip' => null,
            'buyer_nip' => null,
            'is_sale' => true,
        ];
    }
}
