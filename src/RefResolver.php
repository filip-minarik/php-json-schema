<?php

namespace Swaggest\JsonSchema;

use PhpLang\ScopeExit;
use Swaggest\JsonSchema\Constraint\Ref;
use Swaggest\JsonSchema\RemoteRef\BasicFetcher;

class RefResolver
{
    private $resolutionScope;

    private $rootData;

    /** @var Ref[] */
    private $refs = array();

    /** @var RefResolver[] */
    private $remoteRefResolvers = array();

    /** @var RemoteRefProvider */
    private $refProvider;

    /**
     * RefResolver constructor.
     * @param $rootData
     */
    public function __construct($rootData)
    {
        $this->rootData = $rootData;
    }


    public function setRemoteRefProvider(RemoteRefProvider $provider)
    {
        $this->refProvider = $provider;
        return $this;
    }

    private function getRefProvider()
    {
        if (null === $this->refProvider) {
            $this->refProvider = new BasicFetcher();
        }
        return $this->refProvider;
    }


    public function wat1()
    {
        if (isset($schemaArray[self::ID])) {
            $parentScope = $this->resolutionScope;
            $this->resolutionScope = Helper::resolveURI($parentScope, $schemaArray[self::ID]);
            /** @noinspection PhpUnusedLocalVariableInspection */
            $defer = new ScopeExit(function () use ($parentScope) {
                $this->resolutionScope = $parentScope;
            });
        }
    }

    /**
     * @param $referencePath
     * @param string $resolutionScope
     * @return Ref
     * @throws \Exception
     */
    private function resolveReference($referencePath, $resolutionScope = '')
    {
        $ref = &$this->refs[$referencePath];
        if (null === $ref) {
            if ($referencePath[0] === '#') {
                if ($referencePath === '#') {
                    $ref = new Ref($referencePath, $this->rootData);
                } else {
                    $ref = new Ref($referencePath);
                    $path = explode('/', trim($referencePath, '#/'));
                    $branch = &$this->rootData;
                    while (!empty($path)) {
                        $folder = array_shift($path);

                        // unescaping special characters
                        // https://tools.ietf.org/html/draft-ietf-appsawg-json-pointer-07#section-4
                        // https://github.com/json-schema-org/JSON-Schema-Test-Suite/issues/130
                        $folder = str_replace(array('~0', '~1', '%25'), array('~', '/', '%'), $folder);

                        if ($branch instanceof \stdClass && isset($branch->$folder)) {
                            $branch = &$branch->$folder;
                        } elseif (is_array($branch) && isset($branch[$folder])) {
                            $branch = &$branch[$folder];
                        } else {
                            throw new \Exception('Could not resolve ' . $referencePath . ': ' . $folder);
                        }
                    }
                    $ref->setData($branch);
                }
            } else {
                $refParts = explode('#', $referencePath);
                $url = Helper::resolveURI($resolutionScope, $refParts[0]);
                $url = rtrim($url, '#');
                $refLocalPath = isset($refParts[1]) ? '#' . $refParts[1] : '#';
                $refResolver = &$this->remoteRefResolvers[$url];
                if (null === $refResolver) {
                    $refResolver = new RefResolver($this->getRefProvider()->getSchemaData($url));
                }

                $ref = $refResolver->resolveReference($refLocalPath);
            }
        }

        return $this->refs[$referencePath];
    }


}