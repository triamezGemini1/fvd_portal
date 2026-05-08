<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Service/AtletasDomainService.php';

final class AtletasController
{
    private AtletasDomainService $domain;

    public function __construct(AtletasDomainService $domain)
    {
        $this->domain = $domain;
    }

    public function handleMutations(string &$fvd_error): void
    {
        $this->domain->handleMutations($fvd_error);
    }
}
