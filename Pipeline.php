<?php

/**
 * Lenevor Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file license.md.
 * It is also available through the world-wide-web at this URL:
 * https://lenevor.com/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@Lenevor.com so we can send you a copy immediately.
 *
 * @package     Lenevor
 * @subpackage  Base
 * @link        https://lenevor.com
 * @copyright   Copyright (c) 2019 - 2021 Alexander Campo <jalexcam@gmail.com>
 * @license     https://opensource.org/licenses/BSD-3-Clause New BSD license or see https://lenevor.com/license or see /license.md
 */

namespace Syscodes\Routing;

use Throwable;
use Syscodes\Http\Request;
use Syscodes\Contracts\Debug\ExceptionHandler;
use Syscodes\Pipeline\Pipeline as BasePipeline;

/**
 * This extended pipeline catches any exceptions.
 * 
 * @author Alexander Campo <jalexcam@gmail.com>
 */
class Pipeline extends BasePipeline
{
    /**
     * Handle the given exception.
     * 
     * @param  mixed  $passable
     * @param  \Throwable  $e
     * 
     * @return mixed
     * 
     * @throws \Throwable
     */
    protected function handleException($passable, Throwable $e)
    {
        if ( ! $passable instanceof Request) {
            throw $e;
        }
        
        $handler = $this->container->make(ExceptionHandler::class);

        $handler->report($e);

        $response = $handler->render($passable, $e);

        if (is_object($response) && method_exists($response, 'withException')) {
            $response->withException($e);
        }

        return $response;
    }
}