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

namespace BackBee\Rest\EventListener;

use Metadata\MetadataFactory;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator;

use BackBee\Event\Listener\AbstractPathEnabledListener;
use BackBee\Rest\Exception\ValidationException;

/**
 * Pagination listener.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      k.golovin, e.chau <eric.chau@lp-digital.fr>
 */
class PaginationListener extends AbstractPathEnabledListener
{
    /**
     * @var \Metadata\MetadataFactory
     */
    private $metadataFactory;

    /**
     * @var \Symfony\Component\Validator\Validator
     */
    private $validator;

    /**
     * Constructor.
     */
    public function __construct(MetadataFactory $metadataFactory, Validator $validator)
    {
        $this->metadataFactory = $metadataFactory;
        $this->validator = $validator;
    }

    /**
     * Controller.
     *
     * @param FilterControllerEvent $event The event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $request = $this->request = $event->getRequest();
        if (false === $this->isEnabled()) {
            return;
        }

        $controller = $event->getController();

        if (false === in_array($request->getMethod(), array('GET', 'HEAD', 'DELETE'))) {
            // pagination only makes sense with GET or DELETE methods
            return;
        }

        $metadata = $this->getControllerActionMetadata($controller);

        if (null === $metadata || null === $metadata->default_start) {
            // no annotations defined for this controller
            return;
        }

        $start = $metadata->default_start;
        $count = $metadata->default_count;
        if (true === $request->headers->has('Range')) {
            $range = explode(',', $request->headers->get('Range'));
            if (true === isset($range[0])) {
                $start = intval($range[0]);
            }

            if (true === isset($range[1])) {
                $count = intval($range[1]);
            }
        }

        $violations = new ConstraintViolationList();

        $start_violations = $this->validator->validateValue($start, array(
            // NB: Type assert must come first as otherwise it won't be called
            new \Symfony\Component\Validator\Constraints\Type(array(
                'type'    => 'numeric',
                'message' => 'Headers "Range" attribute first operand (=start) must be a positive integer',
            )),
            new \Symfony\Component\Validator\Constraints\Range(array(
                'min'        => 0,
                'minMessage' => 'Headers "Range" attribute first operand (=start) must be a positive integer',
            )),

        ));

        $count_label = 'Headers "Range" attribute second operand (=count)';
        $count_violations = $this->validator->validateValue($count, array(
            // NB: Type assert must come first as otherwise it won't be called
            new \Symfony\Component\Validator\Constraints\Type(array(
                'type' => 'numeric',
                'message' => "$count_label must be a positive integer",
            )),
            new \Symfony\Component\Validator\Constraints\Range(array(
                'min'        => $metadata->min_count,
                'minMessage' => "$count_label must be greater than or equal to ".$metadata->min_count,
                'max'        => $metadata->max_count,
                'maxMessage' => "$count_label must be less than or equal to ".$metadata->max_count,
            )),
        ));

        $violations->addAll($start_violations);
        $violations->addAll($count_violations);

        $violation_param = $this->getViolationsParameterName($metadata);

        if (null !== $violation_param) {
            // if action has an argument for violations, pass it
            $request->attributes->set($violation_param, $violations);
        } elseif (0 < count($violations)) {
            // if action has no arg for violations and there is at least one, throw an exception
            throw new ValidationException($violations);
        }

        // add pagination properties to attributes
        $request->attributes->set('start', $start);
        $request->attributes->set('count', $count);

        // remove pagination properties from headers
        $request->headers->remove('Range');
    }

    /**
     * @param mixed $controller
     *
     * @return \BackBee\Rest\Mapping\ActionMetadata
     */
    protected function getControllerActionMetadata($controller)
    {
        $controllerClass = get_class($controller[0]);

        $metadata = $this->metadataFactory->getMetadataForClass($controllerClass);

        $controllerMetadata = $metadata->getOutsideClassMetadata();

        $action_metadatas = null;
        if (array_key_exists($controller[1], $controllerMetadata->methodMetadata)) {
            $action_metadatas = $controllerMetadata->methodMetadata[$controller[1]];
        }

        return $action_metadatas;
    }

    /**
     * @param \Metadata\ClassHierarchyMetadata|\Metadata\MergeableClassMetadata $metadata
     *
     * @return string|null
     */
    protected function getViolationsParameterName($metadata)
    {
        $param_name = null;
        $violation_list_namespace = 'Symfony\Component\Validator\ConstraintViolationListInterface';
        foreach ($metadata->reflection->getParameters() as $param) {
            if (
                null !== $param->getClass()
                && true === $param->getClass()->implementsInterface($violation_list_namespace)
            ) {
                $param_name = $param->getName();
            }
        }

        return $param_name;
    }
}
