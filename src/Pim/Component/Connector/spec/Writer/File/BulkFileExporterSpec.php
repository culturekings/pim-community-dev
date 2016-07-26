<?php

namespace spec\Pim\Component\Connector\Writer\File;

use Akeneo\Component\FileStorage\Exception\FileTransferException;
use Akeneo\Component\FileStorage\Model\FileInfoInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Pim\Component\Catalog\Model\ProductValue;
use Pim\Component\Catalog\Model\ProductValueInterface;
use Pim\Component\Connector\Writer\File\FileExporterInterface;
use Pim\Component\Connector\Writer\File\FileExporterPathGeneratorInterface;
use Prophecy\Argument;

class BulkFileExporterSpec extends ObjectBehavior
{
    function let(FileExporterInterface $fileExporter, FileExporterPathGeneratorInterface $fileExporterPath)
    {
        $this->beConstructedWith($fileExporter, $fileExporterPath, [
            'pim_catalog_file', 'pim_catalog_image'
        ]);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Pim\Component\Connector\Writer\File\BulkFileExporter');
    }

    function it_copies_media_to_the_export_dir(
        $fileExporter,
        FileInfoInterface $fileInfo1,
        FileInfoInterface $fileInfo2
    ) {
        $fileExporter->export('img/product.jpg', '/tmp/export', 'storageAlias')->shouldBeCalled();
        $fileExporter->export(null, '/tmp/export', 'storageAlias')->shouldNotBeCalled();

        $fileInfo1->getStorage('storageAlias');
        $fileInfo1->getKey('a/b/c/d/product.jpg');
        $fileInfo1->getOriginalFilename('my product.jpg');

        $fileInfo2->getStorage('storageAlias');
        $fileInfo2->getKey(null);
        $fileInfo2->getOriginalFilename(null);

        $productValue1 = new ProductValue();
        $productValue2 = new ProductValue();
        $media = new ArrayCollection([$productValue1, $productValue2]);
        $productValue1->setMedia($fileInfo1);
        $productValue2->setMedia($fileInfo2);

        $this->exportAll($media, sys_get_temp_dir(), 'the_sku');

        $this->exportAll([
            [
                [
                    'filePath'     => 'img/product.jpg',
                    'exportPath'   => 'export',
                    'storageAlias' => 'storageAlias',
                ],
            ],
            [
                [
                    'filePath'     => null,
                    'exportPath'   => 'export',
                    'storageAlias' => 'storageAlias',
                ],
            ],
        ], '/tmp', 'the_identifier');

        $this->getErrors()->shouldHaveCount(0);
    }

    function it_allows_to_get_errors_if_the_copy_went_wrong($fileExporter)
    {
        $fileExporter
            ->export('img/product.jpg', '/tmp/export', 'storageAlias')
            ->willThrow(new FileTransferException());
        $fileExporter
            ->export('wrong/-path.foo', '/tmp/export', 'storageAlias')
            ->willThrow(new \LogicException('Something went wrong.'));

        $this->exportAll([
            [
                [
                    'filePath'     => 'img/product.jpg',
                    'exportPath'   => 'export',
                    'storageAlias' => 'storageAlias',
                ]
            ],
            [
                [
                    'filePath'     => 'wrong/-path.foo',
                    'exportPath'   => 'export',
                    'storageAlias' => 'storageAlias',
                ],
            ],
        ], '/tmp');

        $this->getErrors()->shouldBeEqualTo([
            [
                'message' => 'The media has not been found or is not currently available',
                'meda'  => [
                    'filePath' => 'img/product.jpg',
                ]
            ],
            [
                'message' => 'The media has not been copied. Something went wrong.',
                'media'  => [
                    'filePath' => 'wrong/-path.foo',
                ]
            ]
        ]);
    }
}
