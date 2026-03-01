<?php

namespace Drupal\dmf_distributor_core\Backend\UriRouteResolver;

use DigitalMarketingFramework\Core\Backend\UriRouteResolver\UriRouteResolver;
use Drupal\Core\Url;

class DrupalWebformDataSourceEditUriRouteResolver extends UriRouteResolver
{
    /**
     * @var int
     */
    public const WEIGHT = 0;

    protected function getRouteMatch(): string
    {
        return 'page.data-source.edit';
    }

    protected function match(string $route, array $arguments = []): bool
    {
        if (!parent::match($route, $arguments)) {
            return false;
        }

        $identifier = (string)($arguments['identifier'] ?? '');

        return str_starts_with($identifier, 'webform:');
    }

    protected function doResolve(string $route, array $arguments = []): ?string
    {
        $identifier = (string)($arguments['identifier'] ?? '');
        $returnUrl = $this->getReturnUrl($arguments);

        // Parse identifier: "webform:<webformId>:<handlerId>" or legacy "webform:<webformId>"
        $innerIdentifier = substr($identifier, 8);
        $parts = explode(':', $innerIdentifier, 2);
        $webformId = $parts[0];
        $handlerId = $parts[1] ?? null;

        if ($handlerId !== null) {
            $url = Url::fromRoute('entity.webform.handler.edit_form', [
                'webform' => $webformId,
                'webform_handler' => $handlerId,
            ]);
        } else {
            $url = Url::fromRoute('entity.webform.handlers', ['webform' => $webformId]);
        }

        if ($returnUrl !== '') {
            $url->setOption('query', ['destination' => $returnUrl]);
        }

        return $url->toString();
    }
}
