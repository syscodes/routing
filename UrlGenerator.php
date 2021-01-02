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
 * @author      Javier Alexander Campo M. <jalexcam@gmail.com>
 * @link        https://lenevor.com 
 * @copyright   Copyright (c) 2019-2021 Lenevor Framework 
 * @license     https://lenevor.com/license or see /license.md or see https://opensource.org/licenses/BSD-3-Clause New BSD license
 * @since       0.7.2
 */

namespace Syscodes\Routing;

use Syscodes\Support\Str;
use Syscodes\Http\Request;
use InvalidArgumentException;
use Syscodes\Collections\Arr;

/**
 * Returns the URL generated by the user.
 * 
 * @author Javier Alexander Campo M. <jalexcam@gmail.com>
 */
class UrlGenerator
{
    /**
     * The force URL root.
     * 
     * @var string $forcedRoot 
     */
    protected $forcedRoot;

    /**
     * The force Schema for URLs.
     * 
     * @var string $forcedSchema
     */
    protected $forcedSchema;

    /**
     * The route collection.
     * 
     * @var \Syscodes\Routing\RouteCollection $routes
     */
    protected $routes;
     
    /**
     * The Request instance.
     * 
     * @var string $request
     */
    protected $request;

    /**
     * Constructor. The UrlGenerator class instance.
     * 
     * @param  \Syscodes\Routing\RouteCollection  $route
     * @param  \Syscodes\Http\Request  $request
     * 
     * @return void
     */
    public function __construct(RouteCollection $route, Request $request)
    {
        $this->routes = $route;

        $this->setRequest($request);
    }

    /**
     * Get the current URL for the request.
     * 
     * @return string
     */
    public function current()
    {
        return $this->to($this->request->getPathInfo());
    }

    /**
     * Get the URL for the previous request.
     * 
     * @param  mixed  $fallback  (false by default)
     * 
     * @return string
     */
    public function previous($fallback = false)
    {
        $referer = $this->request->referer();

        $url = $referer ? $this->to($referer) : [];

        if ($url)
        {
            return $url;
        }
        elseif ($fallback)
        {
            return $this->to($fallback);
        }

        return $this->to('/');
    }

    /**
     * Generate a absolute URL to the given path.
     * 
     * @param  string  $path
     * @param  mixed  $options
     * @param  bool|null  $secure
     * 
     * @return string
     */
    public function to($path, $options = [], $secure = null)
    {
        // First we will check if the URL is already a valid URL. If it is we will not
        // try to generate a new one but will simply return the URL as is, which is
        // convenient since developers do not always have to check if it's valid.
        if ($this->isValidUrl($path))
        {
            return $path;
        }

        $scheme = $this->getScheme($secure);

        $tail = implode('/', array_map('rawurlencode', (array) $options));

        $root = $this->getRootUrl($scheme);

        return $this->trimUrl($root, $path, $tail);
    }

    /**
     * Generate a secure, absolute URL to the given path.
     * 
     * @param  string  $path
     * @param  array  $parameters
     * 
     * @return string
     */
    public function secure($path, $parameters = [])
    {
        return $this->to($path, $parameters, true);
    }

    /**
     * Generate a URL to an application asset.
     * 
     * @param  string  $path
     * @param  bool|null  $secure  (null by default)
     * 
     * @return string
     */
    public function asset($path, $secure = null)
    {
        if ($this->isValidUrl($path))
        {
            return $path;
        }

        // Once we get the root URL, we will check to see if it contains an index.php
        // file in the paths. If it does, we will remove it since it is not needed
        // for asset paths, but only for routes to endpoints in the application.
        $root = $this->getRootUrl($this->getScheme($secure));

        return $this->removeIndex($root).'/'.trim($path, '/');
    }
    
    /**
     * Generate a URL to a secure asset.
     * 
     * @param  string  $path
     * 
     * @return string
     */
    public function secureAsset($path)
    {
        return $this->asset($path, true);
    }

    /**
     * Remove the index.php file from a path.
     * 
     * @param  string  $root
     * 
     * @return string
     */
    protected function removeIndex($root)
    {
        $index = 'index.php';

        return Str::contains($root, $index) ? str_replace('/'.$index, '', $root) : $root;
    }

    /**
     * Get the scheme for a raw URL.
     * 
     * @param  bool|null  $secure
     * 
     * @return string
     */
    public function getScheme($secure)
    {
        if (is_null($secure))
        {
            return $this->forcedSchema ?: $this->request->getScheme().'://';
        }

        return $secure ? 'https://' : 'http://';
    }

    /**
     * Force the schema for URLs.
     * 
     * @param  string  $schema
     * 
     * @return void
     */
    public function forcedSchema($schema)
    {
        $this->forcedSchema = $schema.'://'; 
    }

    /**
     * Get the URL to a named route.
     * 
     * @param  string  $name
     * @param  array  $parameters
     * @param  bool  $forced  (true by default)
     * @param  \Syscodes\Routing\Route|null  $route  (null by default)
     * 
     * @return string
     * 
     * @throws \InvalidArgumentException
     */
    public function route($name, array $parameters = [], $forced = true, $route = null)
    {
        if ( ! is_null($route = $route ?? $this->routes->getByName($name)))
        {
            return $this->toRoute($route, $parameters, $forced);
        }

        throw new InvalidArgumentException("Route [{$name}] not defined");
    }

    /**
     * Get the URL for a given route instance.
     * 
     * @param  \Syscodes\Routing\Route  $route
     * @param  mixed  $parameters
     * @param  bool  $forced
     * 
     * @return string
     */
    protected function toRoute($route, $parameters, $forced)
    {
        $domain = $this->getRouteDomain($route);
        $root   = $this->replaceRoot($route, $domain, $parameters);

        $uri = $this->trimUrl($root, $this->replaceRouteParameters($route->getRoute(), $parameters));

        return $forced ? $uri : '/' .ltrim(str_replace($root, '', $uri), '/');
    }
    
    /**
     * Get the URL to a controller action.
     * 
     * @param  string  $action
     * @param  mixed  $parameters
     * @param  bool  $forced  (true by default)
     * 
     * @return string
     * 
     * @throws \InvalidArgumentException
     */
    public function action($action, $parameters = [], $forced = true)
    {
        return $this->route($action, $parameters, $forced, $this->routes->getByAction($action));
    }
    
    /**
     * Replace the parameters on the root path.
     * 
     * @param  \Syscodes\Routing\Route  $route
     * @param  string  $domain
     * @param  array  $parameters
     * 
     * @return string
     */
    protected function replaceRoot($route, $domain, &$parameters)
    {
        return $this->replaceRouteParameters($this->getRouteRoot($route, $domain), $parameters);
    }
    
    /**
     * Replace all of the wildcard parameters for a route path.
     * 
     * @param  string  $path
     * @param  array  $parameters
     * 
     * @return string
     */
    protected function replaceRouteParameters($path, array &$parameters)
    {
        if (count($parameters) > 0)
        {
            $path = preg_replace_sub(
                '/\{.*?\}/', $parameters, $this->replaceNamedParameters($path, $parameters)
            );
        }
        
        return trim(preg_replace('/\{.*?\?\}/', '', $path), '/');
    }
    
    /**
     * Replace all of the named parameters in the path.
     * 
     * @param  string  $path
     * @param  array  $parameters
     * 
     * @return string
     */
    protected function replaceNamedParameters($path, &$parameters)
    {
        return preg_replace_callback('/\{(.*?)\??\}/', function ($match) use (&$parameters)
        {
            return isset($parameters[$match[1]]) ? Arr::pull($parameters, $match[1]) : $match[0];
        }, $path);
    }

    /**
     * Get the formatted domain for a given route.
     * 
     * @param  \Syscodes\Routing\Route  $route
     * 
     * @return string
     */
    protected function getRouteDomain($route)
    {
        return $route->domain() ? $this->formatDomain($route) : null;
    }

    /**
     * Format the domain and port for the route and request.
     * 
     * @param  \Syscodes\Routing\Route  $route
     * 
     * @return string
     */
    protected function formatDomain($route)
    {
        return $this->addPortToDomain($this->getDomainAndScheme($route));
    }

    /**
     * Add the port to the domain if necessary.
     * 
     * @param  string  $domain
     * 
     * @return string
     */
    protected function addPortToDomain($domain)
    {
        if (in_array($this->request->getPort(), [80, 443]))
        {
            return $domain;
        }

        return $domain.':'.$this->request->getPort();
    }

    /**
     * Get the domain and scheme for the route.
     * 
     * @param  \Syscodes\Routing\Route  $route
     * 
     * @return string
     */
    protected function getDomainAndScheme($route)
    {
        return $this->getRouteScheme($route).$route->domain();
    }

    /**
     * Get the root of the route URL.
     * 
     * @param  \Syscodes\Routing\Route  $route
     * @param  string  $domain
     * 
     * @return string
     */
    protected function getRouteRoot($route, $domain)
    {
        return $this->getRootUrl($this->getRouteScheme($route), $domain);
    }

    /**
     * Get the scheme for the given route.
     * 
     * @param  \Syscodes\Routing\Route  $route
     * 
     * @return string
     */
    protected function getRouteScheme($route)
    {
        if ($route->httpOnly)
        {
            return $this->getScheme(false);
        }
        elseif ($route->httpsOnly)
        {
            return $this->getScheme(true);
        }

        return $this->getScheme(null);
    }

    /**
     * Get the base URL for the request.
     * 
     * @param  string  $scheme
     * @param  string|null  $root
     * 
     * @return string
     */
    protected function getRootUrl($scheme, $root = null)
    {
        if (is_null($root))
        {
            $root = $this->forcedRoot ?: $this->request->root();
        }

        $begin = Str::startsWith($root, 'http://') ? 'http://' : 'https://';

        return preg_replace("~$begin~", $scheme, $root, 1);
    }

    /**
     * Set the forced root URL.
     * 
     * @param  string  $root
     * 
     * @return void
     */
    public function forcedRoot($root)
    {
        $this->forcedRoot = $root;
    }
    
    /**
     * Determine if the given path is a valid URL.
     * 
     * @param  string  $path
     * 
     * @return bool
     */
    public function isValidUrl($path)
    {
        if (Str::startsWith($path, ['#', '//', 'mailto:', 'tel:', 'http://', 'https://'])) 
        {
            return true;
        }
        
        return filter_var($path, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Format the given URL segments into a single URL.
     * 
     * @param  string  $root
     * @param  string  $path
     * @param  string  $tail
     * 
     * @return string
     */
    protected function trimUrl($root, $path, $tail = '')
    {
        return trim($root.'/'.trim($path.'/'.$tail, '/'), '/');
    }

    /**
     * Gets the Request instance.
     * 
     * @return \Syscodes\Http\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Sets the current Request instance.
     * 
     * @param  \Syscodes\Http\Request  $request
     * 
     * @return void
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }
}