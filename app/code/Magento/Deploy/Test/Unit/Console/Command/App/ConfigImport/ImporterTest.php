<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Deploy\Test\Unit\Console\Command\App\ConfigImport;

use Magento\Framework\App\DeploymentConfig\ImporterInterface;
use Magento\Framework\App\DeploymentConfig;
use Psr\Log\LoggerInterface as Logger;
use Magento\Deploy\Console\Command\App\ConfigImport\Importer;
use Magento\Deploy\Model\DeploymentConfig\Validator;
use Magento\Deploy\Model\DeploymentConfig\Hash;
use Magento\Deploy\Model\DeploymentConfig\ImporterPool;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Deploy\Model\DeploymentConfig\ImporterFactory;

class ImporterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Validator|\PHPUnit_Framework_MockObject_MockObject
     */
    private $configValidatorMock;

    /**
     * @var ImporterPool|\PHPUnit_Framework_MockObject_MockObject
     */
    private $configImporterPoolMock;

    /**
     * @var ImporterFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    private $importerFactoryMock;

    /**
     * @var DeploymentConfig|\PHPUnit_Framework_MockObject_MockObject
     */
    private $deploymentConfigMock;

    /**
     * @var Hash|\PHPUnit_Framework_MockObject_MockObject
     */
    private $configHashMock;

    /**
     * @var Logger|\PHPUnit_Framework_MockObject_MockObject
     */
    private $loggerMock;

    /**
     * @var OutputInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $outputMock;

    /**
     * @var Importer
     */
    private $importer;

    protected function setUp()
    {
        $this->importerFactoryMock = $this->getMockBuilder(ImporterFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configValidatorMock = $this->getMockBuilder(Validator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configImporterPoolMock = $this->getMockBuilder(ImporterPool::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->deploymentConfigMock = $this->getMockBuilder(DeploymentConfig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHashMock = $this->getMockBuilder(Hash::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->loggerMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->outputMock = $this->getMockBuilder(OutputInterface::class)
            ->getMockForAbstractClass();

        $this->importer = new Importer(
            $this->configValidatorMock,
            $this->configImporterPoolMock,
            $this->importerFactoryMock,
            $this->deploymentConfigMock,
            $this->configHashMock,
            $this->loggerMock
        );
    }

    public function testImport()
    {
        $configData = ['some data'];
        $messages = ['Import has done'];
        $expectsMessages = ['Import has done'];
        $importerClassName = 'someImporterClassName';
        $importers = ['someSection' => $importerClassName];
        $importerMock = $this->getMockBuilder(ImporterInterface::class)
            ->getMockForAbstractClass();

        $this->configImporterPoolMock->expects($this->once())
            ->method('getImporters')
            ->willReturn($importers);
        $this->importerFactoryMock->expects($this->once())
            ->method('create')
            ->with($importerClassName)
            ->willReturn($importerMock);
        $this->configValidatorMock->expects($this->any())
            ->method('isValid')
            ->willReturn(false);
        $this->deploymentConfigMock->expects($this->once())
            ->method('getConfigData')
            ->with('someSection')
            ->willReturn($configData);
        $importerMock->expects($this->once())
            ->method('import')
            ->with($configData)
            ->willReturn($messages);
        $this->configHashMock->expects($this->once())
            ->method('regenerate');
        $this->loggerMock->expects($this->never())
            ->method('error');

        $this->outputMock->expects($this->at(0))
            ->method('writeln')
            ->with('<info>Start import:</info>');
        $this->outputMock->expects($this->at(1))
            ->method('writeln')
            ->with($expectsMessages);

        $this->importer->import($this->outputMock);
    }

    /**
     * @expectedException \Magento\Framework\Exception\RuntimeException
     * @expectedExceptionMessage Import is failed: Some error
     */
    public function testImportWithException()
    {
        $exception = new \Exception('Some error');
        $this->outputMock->expects($this->never())
            ->method('writeln');
        $this->configHashMock->expects($this->never())
            ->method('regenerate');
        $this->configValidatorMock->expects($this->never())
            ->method('isValid');
        $this->deploymentConfigMock->expects($this->never())
            ->method('getConfigData');
        $this->configImporterPoolMock->expects($this->once())
            ->method('getImporters')
            ->willThrowException($exception);
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($exception);

        $this->importer->import($this->outputMock);
    }

    /**
     * @param array $importers
     * @param bool $isValid
     * @dataProvider importNothingToImportDataProvider
     */
    public function testImportNothingToImport(array $importers, $isValid)
    {
        $this->configImporterPoolMock->expects($this->once())
            ->method('getImporters')
            ->willReturn($importers);
        $this->configValidatorMock->expects($this->any())
            ->method('isValid')
            ->willReturn($isValid);
        $this->deploymentConfigMock->expects($this->never())
            ->method('getConfigData');
        $this->configHashMock->expects($this->never())
            ->method('regenerate');
        $this->loggerMock->expects($this->never())
            ->method('error');

        $this->outputMock->expects($this->at(0))
            ->method('writeln')
            ->with('<info>Nothing to import.</info>');

        $this->importer->import($this->outputMock);
    }

    /**
     * @return array
     */
    public function importNothingToImportDataProvider()
    {
        return [
            ['importers' => [], 'isValid' => true],
            ['importers' => [], 'isValid' => false],
            ['importers' => ['someImporter'], 'isValid' => true],
        ];
    }
}
