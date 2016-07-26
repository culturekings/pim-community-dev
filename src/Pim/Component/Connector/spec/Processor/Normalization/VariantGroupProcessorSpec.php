<?php

namespace spec\Pim\Component\Connector\Processor\Normalization;

use Akeneo\Component\Batch\Job\JobParameters;
use Akeneo\Component\Batch\Model\StepExecution;
use Akeneo\Component\FileStorage\Model\FileInfoInterface;
use Akeneo\Component\StorageUtils\Detacher\ObjectDetacherInterface;
use Akeneo\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Pim\Component\Catalog\Model\AttributeInterface;
use Pim\Component\Catalog\Model\GroupInterface;
use Pim\Component\Catalog\Model\ProductTemplateInterface;
use Pim\Component\Catalog\Model\ProductValueInterface;
use Pim\Component\Connector\Writer\File\BulkFileExporter;
use Prophecy\Argument;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class VariantGroupProcessorSpec extends ObjectBehavior
{
    /** @var Filesystem */
    private $filesystem;

    /** @var string */
    private $directory;

    function let(
        NormalizerInterface $normalizer,
        ObjectDetacherInterface $objectDetacher,
        BulkFileExporter $mediaExporter,
        ObjectUpdaterInterface $variantGroupUpdater,
        StepExecution $stepExecution
    ) {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'spec' . DIRECTORY_SEPARATOR;
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->directory);

        $this->beConstructedWith($normalizer, $objectDetacher, $mediaExporter, $variantGroupUpdater);
        $this->setStepExecution($stepExecution);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('\Pim\Component\Connector\Processor\Normalization\VariantGroupProcessor');
    }

    function it_is_an_item_processor()
    {
        $this->shouldImplement('\Akeneo\Component\Batch\Item\ItemProcessorInterface');
    }

    function it_processes_variant_group_without_product_template(
        $objectDetacher,
        $normalizer,
        $stepExecution,
        AttributeInterface $color,
        AttributeInterface $weight,
        GroupInterface $variantGroup,
        JobParameters $jobParameters
    ) {
        $stepExecution->getJobParameters()->willReturn($jobParameters);

        $variantGroup->getProductTemplate()->willReturn(null);
        $variantGroup->getCode()->willReturn('my_variant_group');
        $color->getCode()->willReturn('color');
        $weight->getCode()->willReturn('weight');
        $variantGroup->getAxisAttributes()->willReturn(new ArrayCollection([$color, $weight]));

        $variantStandard = [
            'code' => 'my_variant_group',
            'axis' => ['color', 'weight'],
            'type' => 'variant',
            'labels' => [
                'en_US' => 'My variant group',
                'fr_FR' => 'Mon groupe de variante',
            ]
        ];

        $normalizer->normalize(
            $variantGroup,
            null,
            [
                'with_variant_group_values' => true,
                'identifier'                => 'my_variant_group',
            ]
        )->willReturn($variantStandard);

        $this->process($variantGroup)->shouldReturn($variantStandard);

        $objectDetacher->detach($variantGroup)->shouldBeCalled();
    }

    function it_processes_variant_group_without_media(
        $objectDetacher,
        $normalizer,
        $variantGroupUpdater,
        $stepExecution,
        $mediaExporter,
        ArrayCollection $productValuesCollection,
        ArrayCollection $emptyCollection,
        GroupInterface $variantGroup,
        ProductTemplateInterface $productTemplate,
        ProductValueInterface $productValue,
        JobParameters $jobParameters
    ) {
        $stepExecution->getJobParameters()->willReturn($jobParameters);
        $jobParameters->get('filePath')->willReturn('/my/path/variant_group.csv');

        $variantGroup->getProductTemplate()->willReturn($productTemplate);
        $variantGroup->getCode()->willReturn('my_variant_group');

        $productTemplate->getValuesData()->willReturn([$productValue]);

        $productValuesCollection->filter(Argument::cetera())->willReturn($emptyCollection);
        $emptyCollection->toArray()->willReturn([]);

        $variantStandard = [
            'code' => 'my_variant_group'
        ];

        $normalizer->normalize(
            $variantGroup,
            null,
            [
                'with_variant_group_values' => true,
                'identifier'                => 'my_variant_group',
            ]
        )->willReturn($variantStandard);

        $productTemplate->getValuesData()->willReturn([]);
        $productTemplate->getValues()->willReturn($emptyCollection);
        $mediaExporter->exportAll($emptyCollection, '/my/path', 'my_variant_group')->shouldBeCalled();
        $mediaExporter->getErrors()->willReturn([]);

        $variantGroupUpdater->update($variantGroup, Argument::any())->shouldBeCalled();

        $this->process($variantGroup)->shouldReturn($variantStandard);

        $objectDetacher->detach($variantGroup)->shouldBeCalled();
    }

    function it_processes_a_variant_group_with_several_media(
        $objectDetacher,
        $normalizer,
        $mediaExporter,
        $stepExecution,
        $variantGroupUpdater,
        ArrayCollection $productValuesCollection,
        ArrayCollection $mediaCollection,
        FileInfoInterface $media1,
        FileInfoInterface $media2,
        GroupInterface $variantGroup,
        ProductTemplateInterface $productTemplate,
        ProductValueInterface $productValue,
        JobParameters $jobParameters
    ) {
        $stepExecution->getJobParameters()->willReturn($jobParameters);
        $jobParameters->get('filePath')->willReturn($this->directory . 'variant_group.csv');

        $variantGroup->getProductTemplate()->willReturn($productTemplate);
        $variantGroup->getCode()->willReturn('my_variant_group');

        $productTemplate->getValuesData()->willReturn([$productValue]);
        $mediaCollection->toArray()->willReturn([$media1, $media2]);

        $values = [
            'picture' => [
                'locale' => null,
                'scope'  => null,
                'data'   => ['filePath' => 'a/b/c/d/e/f/little_cat.jpg']
            ],
            'pdf_description' => [
                'locale' => 'en_US',
                'scope'  => null,
                'data'   => ['filePath' => 'a/f/c/c/e/f/little_cat.pdf']
            ]
        ];

        $variantStandard = [
            'code'   => 'my_variant_group',
            'values' => $values,
        ];

        $normalizer->normalize(
            $variantGroup,
            null,
            [
                'with_variant_group_values' => true,
                'identifier'                => 'my_variant_group',
            ]
        )->willReturn($variantStandard);

        $productTemplate->getValuesData()->willReturn($values);

        $variantGroupUpdater->update($variantGroup, Argument::any())->shouldBeCalled();
        $productTemplate->getValues()->willReturn($mediaCollection);
        $mediaExporter->exportAll($mediaCollection, $this->directory, 'my_variant_group')->shouldBeCalled();
        $mediaExporter->getErrors()->willReturn([]);

        $this->process($variantGroup)->shouldReturn($variantStandard);

        $objectDetacher->detach($variantGroup)->shouldBeCalled();
    }

    function it_throws_an_exception_if_media_of_variant_group_is_not_found(
        $objectDetacher,
        $normalizer,
        $denormalizer,
        $stepExecution,
        ArrayCollection $productValuesCollection,
        ArrayCollection $mediaCollection,
        FileInfoInterface $media,
        GroupInterface $variantGroup,
        ProductTemplateInterface $productTemplate,
        ProductValueInterface $productValue,
        JobParameters $jobParameters
    ) {
        $stepExecution->getJobParameters()->willReturn($jobParameters);
        $jobParameters->get('decimalSeparator')->willReturn('.');
        $jobParameters->get('dateFormat')->willReturn('yyyy-MM-dd');

        $variantGroup->getProductTemplate()->willReturn($productTemplate);
        $variantGroup->getCode()->willReturn('my_variant_group');

        $productTemplate->getValuesData()->willReturn([$productValue]);

        $denormalizer->denormalize([$productValue], 'ProductValue[]', 'json')->willReturn($productValuesCollection);

        $productValuesCollection->filter(Argument::cetera())->willReturn($mediaCollection);
        $mediaCollection->toArray()->willReturn([$media]);

        $normalizer->normalize(
            [$media],
            'csv',
            [
                'field_name'   => 'media',
                'prepare_copy' => true,
                'identifier'   => 'my_variant_group'
            ]
        )->willThrow(new FileNotFoundException('upload/path/img.jpg'));

        $objectDetacher->detach($variantGroup)->shouldBeCalled();

        $this->shouldThrow('Akeneo\Component\Batch\Item\InvalidItemException')->duringProcess($variantGroup);
    }
}
