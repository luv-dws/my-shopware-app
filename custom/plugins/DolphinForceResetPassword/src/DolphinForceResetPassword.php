<?php declare(strict_types=1);

namespace DolphinForceResetPassword;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class DolphinForceResetPassword extends Plugin
{
    public const CUSTOM_FIELD_SET_NAME = 'dolphin_customer_force_reset_set';
    public const CUSTOM_FIELD_NAME = 'dolphin_customer_force_reset_password';

    public function install(InstallContext $installContext): void
    {
        $this->createCustomFields($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->removeCustomFields($uninstallContext->getContext());
    }

    private function createCustomFields(Context $context): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET_NAME));
        $existing = $customFieldSetRepository->search($criteria, $context)->first();

        if ($existing) {
            return;
        }

        $customFieldSetRepository->create([
            [
                'name' => self::CUSTOM_FIELD_SET_NAME,
                'config' => [
                    'label' => [
                        'en-GB' => 'Security Settings',
                        'de-DE' => 'Sicherheitseinstellungen',
                    ],
                ],
                'relations' => [
                    [
                        'entityName' => 'customer',
                    ],
                ],
                'customFields' => [
                    [
                        'name' => self::CUSTOM_FIELD_NAME,
                        'type' => CustomFieldTypes::BOOL,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Force Password Reset',
                                'de-DE' => 'Passwort-Zurücksetzen erzwingen',
                            ],
                            'componentName' => 'sw-field',
                            'type' => 'checkbox',
                            'customFieldPosition' => 1,
                        ],
                    ],
                ],
            ],
        ], $context);
    }

    private function removeCustomFields(Context $context): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET_NAME));
        $fieldSet = $customFieldSetRepository->search($criteria, $context)->first();

        if ($fieldSet) {
            $customFieldSetRepository->delete([['id' => $fieldSet->getId()]], $context);
        }
    }
}



