<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Solcre\SolcreFramework2\Common;

use Exception;
use Solcre\SolcreFramework2\Exception\BaseException;
use Solcre\SolcreFramework2\Interfaces\PermissionInterface;
use Solcre\SolcreFramework2\Service\BaseService;
use Laminas\EventManager\Event;
use Laminas\Router\RouteMatch;
use Laminas\Stdlib\Parameters;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\MvcAuth\Identity\IdentityInterface;
use Laminas\ApiTools\Rest\AbstractResourceListener;
use Laminas\ApiTools\Rest\ResourceEvent;

class BaseResource extends AbstractResourceListener
{
    public const NO_PERMISSION = 'permission-undefined';

    protected $service;
    protected $permissionService;

    public function __construct(BaseService $service, ?PermissionInterface $permissionService)
    {
        $this->service = $service;
        $this->permissionService = $permissionService;
    }

    public function dispatch(ResourceEvent $event)
    {
        try {
            $this->event = $event;

            $identityService = $this->service->getIdentityService();
            $identityService->setUserId($this->getLoggedUserId());
            $identityService->setOauthType($this->getLoggedUserOauthType());

            $this->checkPermission($event);

            //Normalized as array query params and adata
            $this->normalizeQueryParams($event);
            $data = (array)$event->getParam('data', []);
            $event->setParam('data', $data);

            //Set page and size to the service
            $request = $event->getRequest();
            if ($request === null) {
                throw new BaseException('Request does not exists', 404);
            }
            $page = $request->getQuery('page', 1);
            $pageSize = $request->getQuery('size', 25);
            $this->service->setCurrentPage($page);
            $this->service->setItemsCountPerPage($pageSize);

            // Remove size parameter
            $parameters = $event->getQueryParams();
            if ($parameters instanceof Parameters & $parameters->offsetExists('size')) {
                $event->getQueryParams()->offsetUnset('size');
            }

            //Normal flow
            return parent::dispatch($event);
        } catch (Exception $exc) {
            $code = $exc->getCode() ?: 404;

            return new ApiProblem($code, $exc->getMessage());
        }
    }

    public function getLoggedUserId($event = null)
    {
        $identity = $this->getIdentity();

        if (! empty($event)) {
            $identity = $event->getIdentity();
        }

        $identityData = $identity->getAuthenticationIdentity();

        return $identityData['user_id'];
    }

    public function getLoggedUserOauthType(Event $event = null)
    {
        $identity = $this->getLoggedUserIdentity($event);
        if ($identity instanceof IdentityInterface) {
            $identityData = $identity->getAuthenticationIdentity();
            return $identityData['oauth_type'];
        }
        return null;
    }

    private function getLoggedUserIdentity(Event $event = null): ?IdentityInterface
    {
        if ($event instanceof ResourceEvent) {
            $identity = $event->getIdentity();
        } else {
            $identity = $this->getIdentity();
        }
        return $identity;
    }

    public function checkPermission(ResourceEvent $event, $permissionName = null, $throwExceptions = true): bool
    {
        $permissionName = empty($permissionName) ? $this->getPermissionName() : $permissionName;
        $loggedUserId = $this->getLoggedUserId($event);
        if ($permissionName === self::NO_PERMISSION || $loggedUserId === null) {
            return true;
        }

        $hasPermission = $this->permissionService->hasPermission($event->getName(), $permissionName, $loggedUserId, $this->getLoggedUserOauthType($event));
        if (! $hasPermission && $throwExceptions) {
            $this->permissionService->throwMethodNotAllowedForCurrentUserException();
        }
        return $hasPermission;
    }

    public function getPermissionName(): string
    {
        return self::NO_PERMISSION;
    }

    protected function normalizeQueryParams(ResourceEvent &$event = null): void
    {
        if ($event === null) {
            return;
        }

        //Get event query params
        $queryParams = $event->getQueryParams() ?: [];
        /* @var $queryParams Parameters */

        //Normalize
        if (($queryParams instanceof Parameters) && $queryParams->count() > 0) {
            //Array for iteration
            $qp = $queryParams->toArray();

            //For each qp
            foreach ($qp as $key => $value) {
                //Check now()
                if ($value === 'now()') {
                    $queryParams->set($key, date('Y-m-d'));
                }

                if ($value === 'null') {
                    $queryParams->set($key, null);
                }
            }

            $event->setQueryParams($queryParams);
        }
    }

    public function getUriParam($key)
    {
        $event = $this->getEvent();

        if ($event instanceof ResourceEvent) {
            $route = $event->getRouteMatch();

            if ($route instanceof RouteMatch) {
                return $route->getParam($key);
            }
        }

        return null;
    }
}
