[tdn:generate:controller](generate-controller.md)
===========================================
Generates a restful crud controller for a specified entity. [[command](../../Command/GenerateControllerCommand.php)]

Usage
-----
```bash
$ ./bin/console tdn:generate:controller [-o|--overwrite] [-d|--target-directory[="..."]] [-r|--resource] [-a|--document-api] <entity>
```

#### Arguments
- entity: The entity to initialize in shortcut format (e.g. MyVendorFooBundle:MyEntity)

#### Options
- overwrite: Overwrites existing files located in directory. **optional**
  <sub>Defaults to false.</sub>
- target-directory: Override the default output directory to specified directory. **optional**
  <sub>Defaults to `<Bundle>/Controller/`.</sub>
- resource: Whether end point is a resource. Returns output similar to [jsonapi standards](http://jsonapi.org/). **optional**
- document-api: Whether to use NelmioApiDocBundle to document your end points. **optional** (recommended)

In addition to this document, you can also pass in the `--help` flag for more information when running the command.

Dependencies
------------
* `<Bundle>/Handler/<Entity>Handler.php`
* `<Bundle>/Form/Type/<Entity>Type.php`

Output
------
By default the output directory will be `<Bundle>/Controller/`.

Files generated:
- `<Entity>Controller.php`

#### Example

Running:
```bash
$ ./bin/console tdn:generate:controller --resource --document-api FooBarBundle:Foo
```

Creates:
```php
<?php

namespace Foo\BarBundle\Controller;

use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use FOS\RestBundle\Controller\FOSRestController;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Foo\BarBundle\Exception\InvalidFormException;
use Foo\BarBundle\Handler\FooHandler;
use Foo\BarBundle\Entity\FooInterface;

/**
 * Foo controller.
 * @package Foo\BarBundle\Controller;
 * @RouteResource("Foo")
 */
class FooController extends FOSRestController
{
    /**
     * Get a Foo
     *
     * @ApiDoc(
     *   description = "Get a Foo.",
     *   resource = true,
     *   requirements={
     *     {
     *        "name"="id",
     *        "dataType"="integer",
     *        "requirement"="\d+",
     *        "description"="Foo identifier."
     *     }
     *   },
     *   output="Foo\BarBundle\Entity\Foo",
     *   statusCodes={
     *     200 = "Foo.",
     *     404 = "Not Found."
     *   }
     * )
     *
     * @View(serializerEnableMaxDepthChecks=true)
     *
     * @param Request $request
     * @param $id
     *
     * @return Response
     */
    public function getAction(Request $request, $id)
    {
        $answer['foo'] = $this->getOr404($id);

        return $answer;
    }

    /**
     * Get all Foo.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Get all Foo.",
     *   output="Foo\BarBundle\Entity\Foo",
     *   statusCodes = {
     *     200 = "List of all Foo",
     *     204 = "No content. Nothing to list."
     *   }
     * )
     *
     * @View(serializerEnableMaxDepthChecks=true)
     *
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return Response
     *
     * @QueryParam(
     *   name="offset",
     *   requirements="\d+",
     *   nullable=true,
     *   description="Offset from which to start listing notes."
     * )
     * @QueryParam(
     *   name="limit",
     *   requirements="\d+",
     *   default="20",
     *   description="How many notes to return."
     * )
     * @QueryParam(
     *   name="order_by",
     *   nullable=true,
     *   array=true,
     *   description="Order by fields. Must be an array ie. &order_by[name]=ASC&order_by[description]=DESC"
     * )
     * @QueryParam(
     *   name="filters",
     *   nullable=true,
     *   array=true,
     *   description="Filter by fields. Must be an array ie. &filters[id]=3"
     * )
     */
    public function cgetAction(ParamFetcherInterface $paramFetcher)
    {
        $offset = $paramFetcher->get('offset');
        $limit = $paramFetcher->get('limit');
        $orderBy = $paramFetcher->get('order_by');
        $criteria = !is_null($paramFetcher->get('filters')) ? $paramFetcher->get('filters') : array();
        $criteria = array_map(function ($item) {
            $item = $item == 'null'?null:$item;
            $item = $item == 'false'?false:$item;
            $item = $item == 'true'?true:$item;

            return $item;
        }, $criteria);

        $result = $this->getFooHandler()
            ->findFoosBy(
                $criteria,
                $orderBy,
                $limit,
                $offset
            );
        //If there are no matches return an empty array
        $answer['foos'] =
            $result ? $result : new ArrayCollection([]);

        return $answer;
    }

    /**
     * Create a Foo.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Create a Foo.",
     *   input="Foo\BarBundle\Form\FooType",
     *   output="Foo\BarBundle\Entity\Foo",
     *   statusCodes={
     *     201 = "Created Foo.",
     *     400 = "Bad Request.",
     *     404 = "Not Found."
     *   }
     * )
     *
     * @View(statusCode=201, serializerEnableMaxDepthChecks=true)
     *
     * @param Request $request
     *
     * @return Response
     */
    public function postAction(Request $request)
    {
        try {
            $new  =  $this->getFooHandler()->post($this->getPostData($request));
            $answer['foo'] = $new;

            return $answer;
        } catch (InvalidFormException $exception) {
            return $exception->getForm();
        }
    }

    /**
     * Update a Foo.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Update a Foo entity.",
     *   input="Foo\BarBundle\Form\FooType",
     *   output="Foo\BarBundle\Entity\Foo",
     *   statusCodes={
     *     200 = "Updated Foo.",
     *     201 = "Created Foo.",
     *     400 = "Bad Request.",
     *     404 = "Not Found."
     *   }
     * )
     *
     * @View(serializerEnableMaxDepthChecks=true)
     *
     * @param Request $request
     * @param $id
     *
     * @return Response
     */
    public function putAction(Request $request, $id)
    {
        try {
            $foo = $this->getFooHandler()
                ->findFooBy(['id'=> $id]);
            if ($foo) {
                $answer['foo'] =
                    $this->getFooHandler()->put(
                        $foo,
                        $this->getPostData($request)
                    );
                $code = Codes::HTTP_OK;
            } else {
                $answer['foo'] =
                    $this->getFooHandler()->post($this->getPostData($request));
                $code = Codes::HTTP_CREATED;
            }
        } catch (InvalidFormException $exception) {
            return $exception->getForm();
        }

        $view = $this->view($answer, $code);

        return $this->handleView($view);
    }

    /**
     * Partial Update to a Foo.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Partial Update to a Foo.",
     *   input="Foo\BarBundle\Form\FooType",
     *   output="Foo\BarBundle\Entity\Foo",
     *   requirements={
     *     {
     *         "name"="id",
     *         "dataType"="integer",
     *         "requirement"="\d+",
     *         "description"="Foo identifier."
     *     }
     *   },
     *   statusCodes={
     *     200 = "Updated Foo.",
     *     400 = "Bad Request.",
     *     404 = "Not Found."
     *   }
     * )
     *
     *
     * @View(serializerEnableMaxDepthChecks=true)
     *
     * @param Request $request
     * @param $id
     *
     * @return Response
     */
    public function patchAction(Request $request, $id)
    {
        $answer['foo'] =
            $this->getFooHandler()->patch(
                $this->getOr404($id),
                $this->getPostData($request)
            );

        return $answer;
    }

    /**
     * Delete a Foo.
     *
     * @ApiDoc(
     *   description = "Delete a Foo entity.",
     *   resource = true,
     *   requirements={
     *     {
     *         "name" = "id",
     *         "dataType" = "integer",
     *         "requirement" = "\d+",
     *         "description" = "Foo identifier"
     *     }
     *   },
     *   statusCodes={
     *     204 = "No content. Successfully deleted Foo.",
     *     400 = "Bad Request.",
     *     404 = "Not found."
     *   }
     * )
     *
     * @View(statusCode=204)
     *
     * @param Request $request
     * @param $id
     * @internal FooInterface $foo
     *
     * @return Response
     */
    public function deleteAction(Request $request, $id)
    {
        $foo = $this->getOr404($id);
        try {
            $this->getFooHandler()
                ->deleteFoo($foo);

            return new Response('', Codes::HTTP_NO_CONTENT);
        } catch (\Exception $exception) {
            throw new \RuntimeException("Deletion not allowed");
        }
    }

    /**
     * Get a entity or throw a exception
     *
     * @param $id
     * @return FooInterface $foo
     */
    protected function getOr404($id)
    {
        $foo = $this->getFooHandler()
            ->findFooBy(['id' => $id]);
        if (!$foo) {
            throw new NotFoundHttpException(sprintf('The resource \'%s\' was not found.', $id));
        }

        return $foo;
    }

    /**
     * Parse the request for the form data
     *
     * @param Request $request
     * @return array
     */
    protected function getPostData(Request $request)
    {
        return $request->request->get('foo', array());
    }

    /**
     * @return FooHandler
     */
    protected function getFooHandler()
    {
        return $this->container->get('foobar.foo.handler');
    }
}

```