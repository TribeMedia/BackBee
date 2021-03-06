<?php

/*
 * Copyright (c) 2011-2015 Lp digital system
 *
 * This file is part of BackBee.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Charles Rouillon <charles.rouillon@lp-digital.fr>
 */

namespace BackBee\ClassContent\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Func;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use BackBee\NestedNode\Page;
use BackBee\Site\Site;

/**
 * AbstractClassContent repository
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      n.dufreche <nicolas.dufreche@lp-digital.fr>
 */
class ClassContentQueryBuilder extends QueryBuilder
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $_em;
    /**
     * @var array
     */
    private $classmap = array(
        'IdxSiteContent' => 'BackBee\ClassContent\Indexes\IdxSiteContent',
        'AbstractClassContent' => 'BackBee\ClassContent\AbstractClassContent',
    );

    /**
     * ClassContentQueryBuilder constructor.
     *
     * @param $em \Doctrine\ORM\EntityManager
     * @param $select \Doctrine\ORM\Query\Expr Use cc as identifier
     */
    public function __construct(EntityManager $em, Func $select = null)
    {
        $this->_em = $em;
        parent::__construct($em);
        $select = is_null($select) ? 'cc' : $select;
        $this->select($select)->distinct()->from($this->getClass('AbstractClassContent'), 'cc');
    }

    /**
     * Add site filter to the query.
     *
     * @param $site mixed (BackBee/Site/Site|String)
     */
    public function addSiteFilter($site)
    {
        if ($site instanceof Site) {
            $site = $site->getUid();
        }
        $this->andWhere(
            'cc._uid IN (SELECT i.content_uid FROM BackBee\ClassContent\Indexes\IdxSiteContent i WHERE i.site_uid = :site_uid)'
        )->setParameter('site_uid', $site);
    }

    /**
     * Set contents uid as filter.
     *
     * @param $uids array
     */
    public function addUidsFilter(array $uids)
    {
        $this->andWhere('cc._uid in(:uids)')->setParameter('uids', $uids);
    }

    /**
     * Add limite to onlinne filter.
     */
    public function limitToOnline()
    {
        $this->leftJoin('cc._mainnode', 'mp');
        $this->andWhere('mp._state IN (:states)')
             ->setParameter('states', array(Page::STATE_ONLINE, Page::STATE_ONLINE | Page::STATE_HIDDEN));
        $this->andWhere('mp._publishing < :today OR mp._publishing IS NULL')
             ->setParameter('today', new \DateTime());
    }

    /**
     * Set a page to filter the query on a nested portion.
     *
     * @param $page BackBee\NestedNode\Page
     */
    public function addPageFilter(Page $page)
    {
        if ($page && !$page->isRoot()) {
            $this->leftJoin('cc._mainnode', 'p')
               ->andWhere('p._root = :selectedPageRoot')
               ->andWhere('p._leftnode >= :selectedPageLeftnode')
               ->andWhere('p._rightnode <= :selectedPageRightnode')
               ->setParameters(array(
                    "selectedPageRoot" => $page->getRoot(),
                    "selectedPageLeftnode" => $page->getLeftnode(),
                    "selectedPageRightnode" => $page->getRightnode(),
                ));
        }
    }

    /**
     * Filter the query by keywords.
     *
     * @param $keywords array
     */
    public function addKeywordsFilter($keywords)
    {
        $contentIds = $this->_em->getRepository('BackBee\NestedNode\KeyWord')
                                ->getContentsIdByKeyWords($keywords);
        if (is_array($contentIds) && !empty($contentIds)) {
            $this->andWhere('cc._uid in(:keywords)')->setParameter('keywords', $contentIds);
        }
    }

    /**
     * Filter by rhe classname descriminator.
     *
     * @param $classes array
     */
    public function addClassFilter($classes)
    {
        if (is_array($classes) && count($classes) !== 0) {
            $filters = array();
            foreach ($classes as $class) {
                $filters[] = 'cc INSTANCE OF \''.$class.'\'';
            }
            $filter = implode(" OR ", $filters);

            $this->andWhere($filter);
        }
    }

    /**
     * Order with the indexation table.
     *
     * @param $label string
     * @param $sort ('ASC'|'DESC')
     */
    public function orderByIndex($label, $sort = 'ASC')
    {
        $this->join('cc._indexation', 'idx')
             ->andWhere('idx._field = :sort')
             ->setParameter('sort', $label)
             ->orderBy('idx._value', $sort);
    }

    /**
     * Get Results paginated.
     *
     * @param $start integer
     * @param $limit integer
     *
     * @return Doctrine\ORM\Tools\Pagination\Paginator
     */
    public function paginate($start, $limit)
    {
        $this->setFirstResult($start)
             ->setMaxResults($limit);

        return new Paginator($this);
    }

    public function addTitleLike($expression)
    {
        if (null !== $expression) {
            $this->andWhere(
                $this->expr()->like(
                    'cc._label',
                    $this->expr()->literal('%'.$expression.'%')
                )
            );
        }
    }

    private function getClass($key)
    {
        if (array_key_exists($key, $this->classmap)) {
            return $this->classmap[$key];
        }
    }
}
