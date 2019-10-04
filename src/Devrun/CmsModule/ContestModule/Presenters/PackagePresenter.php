<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2019
 *
 * @file    PackagePresenter.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\CmsModule\ContestModule\Presenters;

use Devrun\CmsModule\Controls\FlashMessageControl;
use Devrun\CmsModule\Entities\DomainEntity;
use Devrun\CmsModule\Entities\PackageEntity;
use Devrun\CmsModule\Entities\PageEntity;
use Devrun\CmsModule\Entities\RouteEntity;
use Devrun\CmsModule\Facades\PackageFacade;
use Devrun\CmsModule\Presenters\AdminPresenter;
use Devrun\CmsModule\Repositories\DomainRepository;
use Devrun\CmsModule\Repositories\PackageRepository;
use Devrun\Utils\Pattern;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nette\Forms\Container;
use Nette\Forms\Form;
use Nette\Utils\Html;
use Tracy\Debugger;

class PackagePresenter extends AdminPresenter
{

    /** @var PackageRepository @inject */
    public $packageRepository;

    /** @var PackageFacade @inject */
    public $packageFacade;

    /** @var DomainRepository @inject */
    public $domainRepository;



    public function handleDelete($id)
    {
        /** @var PackageEntity $entity */
        if (!$entity= $this->packageRepository->find($id)) {
            $this->flashMessage('Záznam nenalezen', 'danger');
            $this->ajaxRedirect();
        }

        $this->packageRepository->getEntityManager()->remove($entity)->flush();
        $this->packageFacade->onRemovePackage($entity);

        $message = "Balíček `{$entity->getName()}` modulu `{$entity->getModule()}` smazán";
        $this->flashMessage($message, FlashMessageControl::TOAST_TYPE, "Úprava balíčku", FlashMessageControl::TOAST_SUCCESS);


        if ($this->presenter->isAjax()) {
            $this['packageGridControl']->reload();
        }

        $this->ajaxRedirect('this', null, ['flash']);
    }




    protected function createComponentPackageGridControl($name)
    {
        $grid  = $this->createGrid($name);
        $query = $this->packageRepository->createQueryBuilder('a')
            ->addSelect('u')
            ->leftJoin('a.translations', 't')
            ->leftJoin('a.user', 'u');

        if (!$this->user->isAllowed("Cms:Page", 'editAllPackages')) {
            $query->andWhere('a.user = :user')->setParameter('user', $this->user->id);
        }

        /** @var PageEntity[] $packageEntities */
        $packageEntities = $query->getQuery()->getResult();

        $modules  = [];
        $packages = [];
        foreach ($packageEntities as $packageEntity) {
            $modules[$packageEntity->getModule()] = ucfirst($packageEntity->getModule());
            $packages[$packageEntity->getName()]  = ucfirst($packageEntity->getName());
        }


        $domainQuery = $this->domainRepository->createQueryBuilder('a')
            ->where('a.id > 0')
            ->leftJoin('a.user', 'u');


        if (!$this->user->isAllowed("Cms:Page", 'editAllPackages')) {
            $domainQuery->andWhere('a.user = :user')->setParameter('user', $this->user->id);
        }

        /** @var DomainEntity[] $domainEntities */
        $domainEntities = $domainQuery->getQuery()->getResult();

        $domains = [0 => '-- bez domény --'];
        foreach ($domainEntities as $domainEntity) {
            $domains[$domainEntity->id] = $domainEntity->getName();
        }

        $grid->setDataSource($query);

        $grid->addColumnDateTime('inserted', 'Záznam vložen')
            ->setAlign('text-left')
            ->setSortable()
            ->setFitContent()
            ->setFilterDateRange();

        $packageList = array(null => 'Všechny') + $packages;
        $grid->addColumnText('name', 'Název')
            ->setSortable()
            ->setRenderer(function (PackageEntity $row) {

                $html = Html::el('span')->setText($name= $row->getName());

                if ('Default' == $name) {
                    $html->setAttribute('class', 'text-primary');
                }

                return $html;
            })
            ->setFilterSelect($packageList);

        $moduleList = array(null => 'Všechny') + $modules;
        $grid->addColumnText('module', 'Modul')
            ->setSortable()
            ->setFilterSelect($moduleList);


        $grid->addColumnText('user', 'Uživatel', 'user.nickname')
            ->setSortable()
            ->setFilterText()
            ->setCondition(function (\Kdyby\Doctrine\QueryBuilder $queryBuilder, $value) {
                $queryBuilder->andWhere('u.nickname LIKE :nickname')->setParameter('nickname', "%$value%");
            });

        $grid->addColumnText('domain', 'Doména')
            ->setSortable()
            ->setRenderer(function (PackageEntity $row) {
                if (($domain = $row->getDomain()) && $row->getDomain()->getName()) {
                    $html = Html::el('span')
                        ->setText($row->getDomain() ? " {$row->getDomain()->getName()}" : null);

                    if (true === $valid = $domain->isValid()) {
                        $html->setAttribute('class', 'fa fa-check fa-1x text-success');

                    } else {
                        $html->setAttribute('class', 'fa fa-times fa-1x text-danger');
                    }

                    return $html;
                }

                return null;
            })
            ->setFilterText();

        $grid->addColumnText('analyticCode', 'Analytika')
            ->setSortable()
            ->setFilterText();

        $grid->addInlineEdit()->setText('Edit')
            ->onControlAdd[] = function(Container $container) use ($domains) {
            $container->addText('name', '')
                ->addRule(Form::FILLED);

            $container->addSelect('domain', '', $domains)
                ->setAttribute('placeholder', 'domain.cz | www.domain.cz')
                ->addCondition(Form::FILLED)
                ->addRule(Form::PATTERN, 'Zadejte prosím adresu ve tvaru `domain.cz [www.domain.cz]`', Pattern::URL);

            $container->addText('analyticCode', '')
                ->setAttribute('placeholder', 'UA-xxxxx-y  |  GTM-xxxxxxx')
                ->addCondition(Form::FILLED)
                ->addRule(Form::PATTERN, "Zadejte prosím správný kód [UA-xxxxx-y | GTM-xxxxxxx]", Pattern::ANALYTICS);
        };

        $grid->getInlineEdit()->onSetDefaults[] = function(Container $container, PackageEntity $item) {

            $container->setDefaults([
                'id' => $item->id,
                'name' => $item->getName(),
                'domain' =>  $item->getDomain() ? $item->getDomain()->getId() : null,
                'analyticCode' => $item->getAnalyticCode(),
            ]);
        };

        $grid->getInlineEdit()->onSubmit[] = function($id, $values) {

            /** @var PackageEntity $entity */
            if ($entity= $this->packageRepository->find($id)) {

                try {
                    /** @var DomainEntity $domainEntity */
                    if (!$values->domain || (!$domainEntity = $this->domainRepository->find($values->domain))) {
                        $domainEntity = null;
                    }

                    /** @var RouteEntity[] $sourceRoutes */
                    $sourceRoutes = $this->packageRepository->getSourceRoutes($entity, $entity);
                    foreach ($sourceRoutes as $sourceRoute) {
                        if ($domainEntity) {

                            // homepage = '' domain url
                            if ($sourceRoute->getPage()->lvl == 0) {
                                $domainUrl = '';

                            } else {
                                // other page = some domain url
                                $urlParts = explode('/', $sourceRoute->getUrl());
                                $domainUrl = end($urlParts);
                            }
                            $sourceRoute->setDomainUrl($domainUrl);
                        }

                        $sourceRoute->setDomain($domainEntity);
                        $this->packageRepository->getEntityManager()->persist($sourceRoute);
                        $sourceRoute->mergeNewTranslations();
                    }

                    $entity
                        ->setAnalyticCode($values->analyticCode)
                        ->setDomain($domainEntity);

                    $entity->mergeNewTranslations();
                    $this->packageRepository->getEntityManager()->persist($entity)->flush();

                    $message = "Doména balíčku `{$entity->getName()}` upravena.";
                    $this->flashMessage($message, FlashMessageControl::TOAST_TYPE, "Úprava domény", FlashMessageControl::TOAST_SUCCESS);


                } catch (UniqueConstraintViolationException $exception) {
                    $message = "Chyba při změně domény, přesvěčte se, že jsou url adresy unikátní.";
                    $this->flashMessage($message, FlashMessageControl::TOAST_TYPE, "Úprava domény", FlashMessageControl::TOAST_WARNING);

                }

                $this->ajaxRedirect('this', null, ['flash']);
                $this->packageFacade->onChangePackage($entity);
            }

        };

        $grid->addAction('delete', 'Smazat', 'delete!')
            ->setIcon('trash fa-2x')
            ->setClass('ajax btn btn-xs btn-danger')
            ->setConfirm(function ($item) {
                return "Opravdu chcete smazat balíček {$item->name} - {$item->module}?";
            });


        $grid->allowRowsAction('delete', function(PackageEntity $packageEntity) {
            return $packageEntity->getName() == 'Default'
                ? $this->user->isAllowed('Cms:Contest:Package', 'deleteDefault')
                : true;
        });


        return $grid;
    }


}