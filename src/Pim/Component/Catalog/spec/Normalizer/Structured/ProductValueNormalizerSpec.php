<?php

namespace spec\Pim\Component\Catalog\Normalizer\Structured;

use PhpSpec\ObjectBehavior;
use Pim\Component\Catalog\AttributeTypes;
use Pim\Component\Catalog\Model\AttributeInterface;
use Pim\Component\Catalog\Model\ProductValueInterface;
use Akeneo\Component\Localization\Localizer\DateLocalizer;
use Akeneo\Component\Localization\Localizer\NumberLocalizer;
use Prophecy\Argument;
use Symfony\Component\Serializer\SerializerInterface;

class ProductValueNormalizerSpec extends ObjectBehavior
{
    function let(SerializerInterface $serializer)
    {
        $serializer->implement('Symfony\Component\Serializer\Normalizer\NormalizerInterface');
        $this->setSerializer($serializer);
    }

    function it_is_a_normalizer()
    {
        $this->shouldImplement('Symfony\Component\Serializer\Normalizer\NormalizerInterface');
    }

    function it_supports_attribute_type(ProductValueInterface $productValue)
    {
        $this->supportsNormalization($productValue, 'xml')->shouldReturn(true);
        $this->supportsNormalization($productValue, 'json', [])->shouldReturn(true);
        $this->supportsNormalization($productValue, 'csv')->shouldReturn(false);
        $this->supportsNormalization($productValue, 'flat')->shouldReturn(false);
    }

    function it_normalizes_number_with_decimal(
        $serializer,
        ProductValueInterface $productValue,
        AttributeInterface $attribute
    ) {
        $productValue->getData()->willReturn(25.3);
        $productValue->getScope()->willReturn(null);
        $productValue->getLocale()->willReturn(null);
        $attribute->getAttributeType()->willReturn(AttributeTypes::NUMBER);
        $productValue->getAttribute()->willReturn($attribute);
        $serializer->normalize(25.3, 'json', [])->shouldBeCalled()->willReturn(25.3);

        $this->normalize($productValue, 'json', [])->shouldReturn([
            'locale' => null,
            'scope'  => null,
            'data'   => 25.3
        ]);
    }

    function it_normalizes_number_without_decimal(
        $serializer,
        ProductValueInterface $productValue,
        AttributeInterface $attribute
    ) {
        $productValue->getData()->willReturn(25);
        $productValue->getScope()->willReturn(null);
        $productValue->getLocale()->willReturn(null);
        $attribute->getAttributeType()->willReturn(AttributeTypes::NUMBER);
        $productValue->getAttribute()->willReturn($attribute);
        $serializer->normalize(25, 'json', [])->shouldBeCalled()->willReturn(25);

        $this->normalize($productValue, 'json', [])->shouldReturn([
            'locale' => null,
            'scope'  => null,
            'data' => 25
        ]);
    }

    function it_normalizes_empty_number(
        $serializer,
        ProductValueInterface $productValue,
        AttributeInterface $attribute
    ) {
        $productValue->getData()->willReturn('');
        $productValue->getScope()->willReturn(null);
        $productValue->getLocale()->willReturn(null);
        $attribute->getAttributeType()->willReturn(AttributeTypes::NUMBER);
        $productValue->getAttribute()->willReturn($attribute);
        $serializer->normalize('', 'json', [])->shouldBeCalled()->willReturn('');

        $this->normalize($productValue, 'json', [])->shouldReturn([
            'locale' => null,
            'scope'  => null,
            'data' => ''
        ]);
    }

    function it_normalizes_product_value_which_is_not_a_number(
        $serializer,
        ProductValueInterface $productValue,
        AttributeInterface $attribute
    ) {
        $productValue->getData()->willReturn('shoes');
        $productValue->getScope()->willReturn(null);
        $productValue->getLocale()->willReturn(null);
        $attribute->getAttributeType()->willReturn(AttributeTypes::TEXT);
        $productValue->getAttribute()->willReturn($attribute);
        $serializer->normalize('shoes', 'json', [])->shouldBeCalled()->willReturn('shoes');

        $this->normalize($productValue, 'json', [])->shouldReturn([
            'locale' => null,
            'scope'  => null,
            'data'   => 'shoes'
        ]);
    }

    function it_normalizes_date(
        $serializer,
        ProductValueInterface $productValue,
        AttributeInterface $attribute
    ) {
        $productValue->getData()->willReturn('2000-10-28');
        $productValue->getScope()->willReturn(null);
        $productValue->getLocale()->willReturn(null);
        $attribute->getAttributeType()->willReturn(AttributeTypes::DATE);
        $productValue->getAttribute()->willReturn($attribute);
        $serializer->normalize('2000-10-28', 'json', [])->willReturn('2000-10-28');

        $this->normalize($productValue, 'json', [])->shouldReturn([
            'locale' => null,
            'scope'  => null,
            'data'   => '2000-10-28'
        ]);
    }
}
