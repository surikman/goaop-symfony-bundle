<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\Tests;

use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Symfony\GoAopBundle\DependencyInjection\Compiler\AspectCollectorPass;
use Go\Symfony\GoAopBundle\GoAopBundle;
use PHPUnit\Framework\MockObject\Invocation;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Debug\DebugClassLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GoAopBundleTest
 */
class GoAopBundleTest extends TestCase
{
    /**
     * @test
     */
    public function itThrowsExceptionWhenBundleIsNotRegisteredAsFirstBundle()
    {
        $container = $this->getMockBuilder(ContainerBuilder::class)->disableOriginalConstructor()->getMock();

        $container
            ->method('getParameter')
            ->with('kernel.bundles')
            ->willReturn(['ArbitraryBundleName' => 'A bundle']);

        $bundle = new GoAopBundle();

        $bundle->getName(); // invoke resolution of bundle name

        $this->expectException(\InvalidArgumentException::class);
        $bundle->build($container);
    }

    /**
     * @test
     */
    public function itRegistersAspectCollectorPassPass()
    {
        $container = $this->getMockBuilder(ContainerBuilder::class)->disableOriginalConstructor()->getMock();

        $container
            ->method('getParameter')
            ->with('kernel.bundles')
            ->willReturn(['GoAopBundle' => 'A bundle']);

        $container
            ->expects($spy = $this->exactly(1))
            ->method('addCompilerPass');

        $bundle = new GoAopBundle();

        $bundle->getName(); // invoke resolution of bundle name

        $bundle->build($container);

        $invocationReflection = new ReflectionClass(InvocationOrder::class);
        $invocationPropertyReflection = $invocationReflection->getProperty('invocations');
        $invocationPropertyReflection->setAccessible(true);

        /** @var Invocation $invocation */
        $invocation = $invocationPropertyReflection->getValue($spy)[0];
        $this->assertInstanceOf(AspectCollectorPass::class, $invocation->getParameters()[0]);
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function itBoots()
    {
        require_once __DIR__.'/Fixtures/mock/DebugClassLoader.php';
        require_once __DIR__.'/Fixtures/mock/AopComposerLoader.php';

        $container = $this->getMockBuilder(ContainerInterface::class)->disableOriginalConstructor()->getMock();

        $container
            ->expects($this->once())
            ->method('get')
            ->with('goaop.aspect.container');

        $bundle = new GoAopBundle();
        $bundle->setContainer($container);

        DebugClassLoader::reset();
        DebugClassLoader::enable();
        $this->assertTrue(DebugClassLoader::$enabled);

        $bundle->boot();

        $this->assertTrue(DebugClassLoader::$enabled);
        $this->assertEquals(['enable', 'disable', 'enable'], DebugClassLoader::$invocations);
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function itThrowsExceptionOnBootWithoutAopComposerLoader()
    {
        require_once __DIR__.'/Fixtures/mock/DebugClassLoader.php';
        require_once __DIR__.'/Fixtures/mock/AopComposerLoader.php';

        $container = $this->getMockBuilder(ContainerInterface::class)->disableOriginalConstructor()->getMock();

        $container
            ->expects($this->once())
            ->method('get')
            ->with('goaop.aspect.container');

        $bundle = new GoAopBundle();
        $bundle->setContainer($container);

        AopComposerLoader::$initialized = false;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Initialization of AOP loader was failed, probably due to Debug::enable()');
        $bundle->boot();
    }
}
