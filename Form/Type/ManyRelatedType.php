<?php

namespace Tdn\PilotBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;
use Doctrine\Common\Persistence\ObjectManager;
use Tdn\PilotBundle\Form\DataTransformer\ManyRelatedTransformer;
use Tdn\PilotBundle\Form\DataTransformer\ArrayToStringTransformer;

/**
 * Class ManyRelatedType
 * @package Tdn\PilotBundle\Form\Type
 */
class ManyRelatedType extends AbstractType
{
    /**
     * @var ObjectManager
     */
    private $entityManager;

    /**
     * @param ObjectManager $entityManager
     */
    public function __construct(ObjectManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $transformer = new ManyRelatedTransformer($this->entityManager, $options['entityName']);
        $viewTransformer = new ArrayToStringTransformer();
        $builder->addModelTransformer($transformer);
        $builder->addViewTransformer($viewTransformer);
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setRequired(['entityName']);

        if ($resolver instanceof OptionsResolver) {
            $resolver->setAllowedTypes('entityName', ['string']);
            $resolver->setDefined(['entityName']);
            $resolver->setDefault('invalid_message', function (Options $options) {
                return 'This value is not valid.  Unable to find ' . $options['entityName'] . ' in the database.';
            });
        }
    }

    public function getParent()
    {
        return 'text';
    }

    public function getName()
    {
        return 'tdn_many_related';
    }
}
