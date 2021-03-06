<?php

namespace Akeneo\Component\Localization\Presenter;

use Akeneo\Component\Localization\Factory\DateFactory;

/**
 * Date presenter, able to render date readable for a human
 *
 * @author    Pierre Allard <pierre.allard@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DatePresenter implements PresenterInterface
{
    /** @var DateFactory */
    protected $dateFactory;

    /** @var string[] */
    protected $attributeTypes;

    /**
     * @param DateFactory $dateFactory
     * @param string[]    $attributeTypes
     */
    public function __construct(DateFactory $dateFactory, array $attributeTypes)
    {
        $this->dateFactory    = $dateFactory;
        $this->attributeTypes = $attributeTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function present($value, array $options = [])
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        if (!($value instanceof \DateTime)) {
            $value = new \DateTime($value);
        }

        $formatter = $this->dateFactory->create($options);

        return $formatter->format($value);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($attributeType)
    {
        return in_array($attributeType, $this->attributeTypes);
    }
}
