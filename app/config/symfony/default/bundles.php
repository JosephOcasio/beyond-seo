<?php

use App\Symfony\Bundles\FrameworkBundle;
use DDD\DDDBundle;

return [
	/*Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],*/
	/*Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],*/
	FrameworkBundle::class => ['all' => true],
	Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle::class => ['all' => true],
	Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
	Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
	DDDBundle::class => ['all' => true]
];