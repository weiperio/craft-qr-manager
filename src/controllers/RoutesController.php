<?php

namespace weiperio\craftqrmanager\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\UrlHelper;
use craft\helpers\StringHelper;
use craft\helpers\Cp;

use weiperio\craftqrmanager\QrManager;
use weiperio\craftqrmanager\models\Route;
use weiperio\craftqrmanager\elements\Route as RouteElement;

use craft\controllers\ElementsController;

/**
 * Route controller
 */
class RoutesController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * qr-manager/route action
     */
    public function actionIndex(): Response
    {
        // ...
        // Check for selected site param
        $selectedSite = Craft::$app->getRequest()->getParam('site');
        if ($selectedSite) {
            $selectedSiteId = Craft::$app->getSites()->getSiteByHandle($selectedSite)->id;
        }
        // Render the template
        return $this->renderTemplate('qr-manager/routes/index', [
            'selectedSiteId' => $selectedSiteId ?? null,
        ]);
    }

    /**
     * qr-manager/routes/create action
     */
    public function actionCreate(): Response
    {
        $route = Craft::createObject(RouteElement::class);
        $route->title = Craft::t('qr-manager', Craft::$app->getRequest()->getParam('title') ?? '');
        // Get the redirect uri param
        $redirectUri = Craft::$app->getRequest()->getParam('redirectUri');
        if ($redirectUri != null) {
            $route->redirectUri = $redirectUri;
        }
        // Get the site param
        $siteParam = Craft::$app->getRequest()->getParam('site');
        if ($siteParam != null) {
            $enabledForSites = [];
            $sites = Craft::$app->getSites()->getAllSites();
            foreach ($sites as $site) {
                if ($site->handle == $siteParam) {
                    $route->siteId = $site->id;
                    array_push($enabledForSites, true);
                } else {
                    array_push($enabledForSites, false);
                }
            }
            // Set the enabledForSite to the current site
            $route->setEnabledForSite($enabledForSites);
        }
        $user = static::currentUser();

        // Save it
        $success = Craft::$app->getDrafts()->saveElementAsDraft($route, $user->id, null, false);

        if (!$success) {
            return $this->asModelFailure($route, Craft::t('app', 'Couldn’t create {type}.', [
                'type' => RouteElement::lowerDisplayName(),
            ]), 'route');
        }

        $editUrl = $route->getCpEditUrl();

        $response = $this->asCpScreen(Craft::$app->runAction('elements/edit', [
            'elementType' => RouteElement::class,
            'elementId' => $route->id,
            'element' => $route]));

        // Send as cpScreen

        // if (!$this->request->getAcceptsJson()) {
        //     $response->redirect(UrlHelper::urlWithParams($editUrl, [
        //         'fresh' => 1,
        //     ]));
        // }

        // Call the element edit controller as cpScreen
        return $response;
    }


    /**
     * qr-manager/routes/edit action
     */
    public function actionEdit(int $routeId = null): Response
    {
        // Check the user permissions
        $this->requirePermission('qr-manager:editRoutes');

        // Check if we have a route ID
        if ($routeId) {
            // Get site from request
            $site = Craft::$app->getRequest()->getParam('site');
            // If the site is not set, use the current site
            if (!$site) {
                $site = Craft::$app->getSites()->getCurrentSite()->handle;
            }
            $siteId = Craft::$app->getSites()->getSiteByHandle($site)->id;
            $route = QrManager::getInstance()->routes->getRouteById($routeId, $siteId);

            if (!$route) {
                throw new NotFoundHttpException('Route not found');
            }
        } else {
            // Create the element

            $route = Craft::createObject(RouteElement::class);
            $route->title = Craft::t('qr-manager', 'New Route');
            $user = static::currentUser();
            $success = Craft::$app->getDrafts()->saveElementAsDraft($route, $user->id, markAsSaved: false);
            // Craft::$app->elements->saveElement($route, false);
        }

        $site = Craft::$app->getSites()->getCurrentSite();
        $siteId = $site->id;
        // Check if the request has a siteHandle
        $siteHandle = Craft::$app->getRequest()->getParam('site');
        if ($siteHandle) {
            // Get the site id using the siteHandle
            $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
            $siteId = $site->id;
        }
        $siteSettings = QrManager::getInstance()->settingsService->getSiteSettings($siteId);

        // Call the element edit controller as cpScreen
        return $this->asCpScreen(Craft::$app->runAction('elements/edit', [
            'elementType' => RouteElement::class,
            'elementId' => $route->id,
            'element' => $route,
            'selectedSiteId' => $siteId ?? null,
            'settings' => $siteSettings,
        ]));
    }

    /**
     * qr-manager/routes/delete action
     */
    public function actionDelete(): Response
    {
        // Check the user permissions
        $this->requirePermission('qr-manager:editRoutes');

        $this->requirePostRequest();

        $routeId = Craft::$app->getRequest()->getRequiredBodyParam('routeId');
        $site = Craft::$app->getRequest()->getParam('site');
        // If the site is not set, use the current site
        if (!$site) {
            $site = Craft::$app->getSites()->getCurrentSite()->handle;
        }
        $siteId = Craft::$app->getSites()->getSiteByHandle($site)->id;

        $route = QrManager::getInstance()->routes->getRouteById($routeId, $siteId);

        if (!$route) {
            throw new NotFoundHttpException('Route not found');
        }

        if (!Craft::$app->getElements()->deleteElement($route)) {
            return $this->asErrorJson('Couldn’t delete route.');
        }

        return $this->asJson(['success' => true]);
    }
}
