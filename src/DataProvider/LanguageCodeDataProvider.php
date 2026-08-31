<?php

namespace Drupal\dmf_distributor_core\DataProvider;

use DigitalMarketingFramework\Core\Context\WriteableContextInterface;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\ContainerSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\SchemaInterface;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\StringSchema;
use DigitalMarketingFramework\Distributor\Core\DataProvider\DataProvider;
use DigitalMarketingFramework\Distributor\Core\Model\DataSet\SubmissionDataSetInterface;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Data provider for the language of the current page.
 *
 * The language code is determined while the submission context is being
 * built, so that asynchronous jobs use the language of the original request
 * instead of the language of the worker process.
 */
class LanguageCodeDataProvider extends DataProvider
{
    public const KEY_FIELD = 'field';

    public const KEY_DEFAULT_LANGUAGE = 'defaultLanguage';

    public const DEFAULT_FIELD = 'language';

    /**
     * Constructs a LanguageCodeDataProvider object.
     *
     * @param string $keyword
     *   The plugin keyword
     * @param RegistryInterface $registry
     *   The distributor registry
     * @param SubmissionDataSetInterface $submission
     *   The submission being processed
     * @param LanguageManagerInterface $languageManager
     *   The language manager
     */
    public function __construct(
        string $keyword,
        RegistryInterface $registry,
        SubmissionDataSetInterface $submission,
        protected LanguageManagerInterface $languageManager,
    ) {
        parent::__construct($keyword, $registry, $submission);
    }

    protected function getLanguageCode(): string
    {
        return $this->languageManager->getCurrentLanguage()->getId();
    }

    protected function processContext(WriteableContextInterface $context): void
    {
        if (isset($context['language']) && $context['language'] !== '') {
            return;
        }

        $language = $this->getLanguageCode();

        if ($language === '') {
            $language = $this->getConfig(static::KEY_DEFAULT_LANGUAGE);
        }

        if ($language !== '') {
            $context['language'] = $language;
        }
    }

    protected function process(): void
    {
        $language = $this->submission->getContext()['language'] ?? null;
        if (isset($language)) {
            $this->setField(
                $this->getConfig(static::KEY_FIELD),
                $language
            );
        }
    }

    public static function getSchema(): SchemaInterface
    {
        /** @var ContainerSchema $schema */
        $schema = parent::getSchema();
        $schema->addProperty(static::KEY_FIELD, new StringSchema(static::DEFAULT_FIELD));

        $schema->addProperty(static::KEY_DEFAULT_LANGUAGE, new StringSchema());

        return $schema;
    }
}
