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

namespace Syscodes\Components\Routing;

use LogicExcption;
use DomainException;
use Syscodes\Components\Collections\Arr;

/**
 * Allows compile 
 * 
 * @author Alexander Campo <jalexcam@gmail.com>
 */
class RouteCompiler
{
    /**
     * The Route implementation.
     * 
     * @var \Syscodes\Components\Routing\Route $route
     */
    protected $route;

    /**
     * Constructor. Create a new Route Compiler instance.
     * 
     * @param  \Syscodes\Components\Routing\Route  $route
     * 
     * @return void
     */
    public function __construct(Route $route)
    {
        $this->route = $route;
    }

    /**
     * Compile the inner Route pattern.
     * 
     * @return string
     * 
     * @throws \LogicException|\DomainException
     */
    public function compile()
    {
        $uri       = with($route = $this->route)->getRoute();
        $patterns  = $route->getPatterns();
        $optionals = 0;
        $variables = [];

        $pattern = preg_replace_callback('~\{(.*?)(\?)?\}~', function ($matches) use ($uri, $patterns, &$optionals, &$variables) {
            [$name, $optional] = array_pad($matches, 3, $patterns);
            
            if (in_array($name, $variables)) {
                throw new LogicException("Route pattern [{$uri}] cannot reference variable name [{$name}] more than once.");
            } elseif (strlen($name) > 32) {
                throw new DomainException("Variable name [{$name}] cannot be longer than 32 characters in route pattern [{$uri}].");
            } elseif (preg_match('/^\d/', $name) === 1) {
                throw new DomainException("Variable name [{$name}] cannot start with a digit in route pattern [{$uri}].");
            }
            
            $variables[] = $name;

            $pattern = Arr::get($patterns, $name, '[^/]+');
            
            if ($optional) {
                $optionals++;
                
                return sprintf('(?:/(?P<%s>%s)', $name, $pattern);
            } elseif ($optionals > 0) {
                throw new LogicException("Route pattern [{$path}] cannot reference standard variable [{$name}] after optionals.");
            }
            
            return sprintf('/(?P<%s>%s)', $name, $pattern);
        }, $uri);
        
        return sprintf('#^%s%s$#s', $pattern, str_repeat(')?', $optionals));
    }
}