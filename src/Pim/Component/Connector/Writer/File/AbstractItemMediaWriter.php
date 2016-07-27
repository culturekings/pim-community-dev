<?php

namespace Pim\Component\Connector\Writer\File;

use Akeneo\Component\Batch\Item\AbstractConfigurableStepElement;
use Akeneo\Component\Batch\Item\ItemWriterInterface;
use Akeneo\Component\Batch\Job\JobParameters;
use Akeneo\Component\Batch\Model\StepExecution;
use Akeneo\Component\Batch\Step\StepExecutionAwareInterface;
use Akeneo\Component\Buffer\BufferFactory;
use Pim\Component\Catalog\Repository\AttributeRepositoryInterface;
use Pim\Component\Connector\ArrayConverter\ArrayConverterInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author    Marie Bochu <marie.bochu@akeneo.com>
 * @copyright 2016 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
abstract class AbstractItemMediaWriter extends AbstractConfigurableStepElement implements
    ItemWriterInterface,
    StepExecutionAwareInterface
{
    /** @var ArrayConverterInterface */
    protected $arrayConverter;

    /** @var FlatItemBufferFlusher */
    protected $flusher;

    /** @var BufferFactory */
    protected $bufferFactory;

    /** @var AttributeRepositoryInterface */
    protected $attributeRepository;

    /** @var FileExporterPathGeneratorInterface */
    protected $fileExporterPath;

    /** @var array */
    protected $mediaAttributeTypes;

    /** @var OptionsResolver */
    protected $filePathResolver;

    /** @var StepExecution */
    protected $stepExecution;

    /** @var array */
    protected $filePathResolverOptions;

    /** @var Filesystem */
    protected $localFs;

    /** @var array */
    protected $writtenFiles = [];

    /** @var FlatItemBuffer */
    protected $flatRowBuffer = null;

    /**
     * @param ArrayConverterInterface            $arrayConverter
     * @param BufferFactory                      $bufferFactory
     * @param FlatItemBufferFlusher              $flusher
     * @param AttributeRepositoryInterface       $attributeRepository
     * @param FileExporterPathGeneratorInterface $fileExporterPath
     * @param array                              $mediaAttributeTypes
     */
    public function __construct(
        ArrayConverterInterface $arrayConverter,
        BufferFactory $bufferFactory,
        FlatItemBufferFlusher $flusher,
        AttributeRepositoryInterface $attributeRepository,
        FileExporterPathGeneratorInterface $fileExporterPath,
        array $mediaAttributeTypes
    ) {
        $this->arrayConverter = $arrayConverter;
        $this->bufferFactory = $bufferFactory;
        $this->flusher = $flusher;
        $this->attributeRepository = $attributeRepository;
        $this->mediaAttributeTypes = $mediaAttributeTypes;
        $this->fileExporterPath = $fileExporterPath;

        $this->filePathResolver = new OptionsResolver();
        $this->filePathResolver->setRequired('parameters');
        $this->filePathResolver->setAllowedTypes('parameters', 'array');

        $this->filePathResolverOptions = [
            'parameters' => ['%datetime%' => date('Y-m-d_H:i:s')]
        ];

        $this->localFs = new Filesystem();
    }

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        if (null === $this->flatRowBuffer) {
            $this->flatRowBuffer = $this->bufferFactory->create();
        }

        $exportDirectory = dirname($this->getPath());
        if (!is_dir($exportDirectory)) {
            $this->localFs->mkdir($exportDirectory);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $items)
    {
        $parameters = $this->stepExecution->getJobParameters();
        $converterOptions = $this->getConverterOptions($parameters);

        $flatItems = [];
        foreach ($items as $item) {
            if ($parameters->has('with_media') && $parameters->get('with_media')) {
                $directory = $this->getWorkingDirectory($parameters->get('filePath'));
                $item = $this->archiveMedia($item, $directory);
            }

            $flatItems[] = $this->arrayConverter->convert($item, $converterOptions);
        }

        $options = [];
        $options['withHeader'] = $parameters->get('withHeader');
        $this->flatRowBuffer->write($flatItems, $options);
    }

    /**
     * Flush items into a file
     */
    public function flush()
    {
        $this->flusher->setStepExecution($this->stepExecution);

        $parameters = $this->stepExecution->getJobParameters();

        $writtenFiles = $this->flusher->flush(
            $this->flatRowBuffer,
            $this->getConfigurationWriter(),
            $this->getPath(),
            ($parameters->has('linesPerFile') ? $parameters->get('linesPerFile') : -1)
        );

        foreach ($writtenFiles as $writtenFile) {
            $this->writtenFiles[$writtenFile] = basename($writtenFile);
        }
    }

    /**
     * Get the file path in which to write the data
     *
     * @return string
     */
    public function getPath()
    {
        $parameters = $this->stepExecution->getJobParameters();
        $filePath = $parameters->get('filePath');

        if ($parameters->has('mainContext')) {
            $mainContext = $parameters->get('mainContext');
            foreach ($mainContext as $key => $value) {
                $this->filePathResolverOptions['parameters']['%' . $key . '%'] = $value;
            }
        }

        $options = $this->filePathResolver->resolve($this->filePathResolverOptions);

        return strtr($filePath, $options['parameters']);
    }

    /**
     * {@inheritdoc}
     */
    public function getWrittenFiles()
    {
        return $this->writtenFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    /**
     * Get configuration for writer (type of export, delimiter, enclosure, etc)
     *
     * @return array
     */
    abstract protected function getConfigurationWriter();

    /**
     * Return the identifier of the item (e.q sku or variant group code)
     *
     * @param array $item
     *
     * @return string
     */
    abstract protected function getIdentifier(array $item);

    /**
     * Stock media for archiver and change media path
     *
     * @param array  $item
     * @param string $tmpDirectory
     *
     * @return array
     */
    protected function archiveMedia(array $item, $tmpDirectory)
    {
        $attributeTypes = $this->attributeRepository->getAttributeTypeByCodes(array_keys($item['values']));
        $mediaAttributeTypes = array_filter($attributeTypes, function ($attributeCode) {
            return in_array($attributeCode, $this->mediaAttributeTypes);
        });

        $identifier = $this->getIdentifier($item);

        foreach ($mediaAttributeTypes as $attributeCode => $attributeType) {
            foreach ($item['values'][$attributeCode] as $index => $value) {
                if (null !== $value['data']) {
                    $exportDirectory = $this->fileExporterPath->generate($value, [
                        'identifier' => $identifier,
                        'code'       => $attributeCode,
                    ]);

                    $finder = new Finder();
                    if (is_dir($tmpDirectory . $exportDirectory)) {
                        $files = iterator_to_array($finder->files()->in($tmpDirectory . $exportDirectory));
                        if (!empty($files)) {
                            $path = $exportDirectory . current($files)->getFilename();
                            $this->writtenFiles[$tmpDirectory . $path] = $path;
                            $item['values'][$attributeCode][$index]['data']['filePath'] = $path;
                        }
                    }
                }
            }
        }

        return $item;
    }

    /**
     * @param JobParameters $parameters
     *
     * @return array
     */
    protected function getConverterOptions(JobParameters $parameters)
    {
        $options = [];

        if ($parameters->has('decimalSeparator')) {
            $options['decimal_separator'] = $parameters->get('decimalSeparator');
        }

        if ($parameters->has('dateFormat')) {
            $options['date_format'] = $parameters->get('dateFormat');
        }

        if ($parameters->has('mainContext') && isset($parameters->get('mainContext')['ui_locale'])) {
            $options['locale'] = $parameters->get('mainContext')['ui_locale'];
        }

        return $options;
    }

    /**
     * Build path of the working directory to import media in a specific directory.
     * Will be extracted with TIP-539
     *
     * @param string $filePath
     *
     * @return string
     */
    protected function getWorkingDirectory($filePath)
    {
        $jobExecution = $this->stepExecution->getJobExecution();

        return dirname($filePath)
               . DIRECTORY_SEPARATOR
               . $jobExecution->getJobInstance()->getCode()
               . DIRECTORY_SEPARATOR
               . $jobExecution->getId()
               . DIRECTORY_SEPARATOR;
    }
}