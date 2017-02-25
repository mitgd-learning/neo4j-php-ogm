<?php

namespace GraphAware\Neo4j\OGM\Persisters;

use GraphAware\Common\Cypher\Statement;
use GraphAware\Neo4j\OGM\EntityManager;
use GraphAware\Neo4j\OGM\Metadata\NodeEntityMetadata;
use GraphAware\Neo4j\OGM\Util\DirectionUtils;

class BasicEntityPersister
{
    protected $_className;

    protected $_classMetadata;

    protected $_em;

    public function __construct($className, NodeEntityMetadata $classMetadata, EntityManager $em)
    {
        $this->_className = $className;
        $this->_classMetadata = $classMetadata;
        $this->_em = $em;
    }

    public function loadAll(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        $stmt = $this->getMatchCypher($criteria, $orderBy, $limit, $offset);
        $result = $this->_em->getDatabaseDriver()->run($stmt->text(), $stmt->parameters());

        $hydrator = $this->_em->getEntityHydrator($this->_className);

        return $hydrator->hydrateAll($result);
    }

    public function getSimpleRelationship($alias, $sourceEntity)
    {
        $stmt = $this->getSimpleRelationshipStatement($alias, $sourceEntity);
        $result = $this->_em->getDatabaseDriver()->run($stmt->text(), $stmt->parameters());
        $hydrator = $this->_em->getEntityHydrator($this->_className);

        $hydrator->hydrateSimpleRelationship($alias, $result, $sourceEntity);
    }

    public function getSimpleRelationshipCollection($alias, $sourceEntity)
    {
        $stmt = $this->getSimpleRelationshipCollectionStatement($alias, $sourceEntity);
        $result = $this->_em->getDatabaseDriver()->run($stmt->text(), $stmt->parameters());
        $hydrator = $this->_em->getEntityHydrator($this->_className);

        $hydrator->hydrateSimpleRelationshipCollection($alias, $result, $sourceEntity);
    }

    /**
     * @param $criteria
     * @param null|int $limit
     * @param null|int $offset
     * @param null|array $orderBy
     * @return Statement
     */
    public function getMatchCypher(array $criteria = [], $limit = null, $offset = null, $orderBy = null)
    {
        $identifier = $this->_classMetadata->getEntityAlias();
        $classLabel = $this->_classMetadata->getLabel();
        $cypher  = 'MATCH ('.$identifier.':'.$classLabel.') ';

        $filter_cursor = 0;
        $params = [];

        foreach ($criteria as $key => $criterion) {
            $key     = (string) $key;
            $clause  = $filter_cursor === 0 ? 'WHERE' : 'AND';
            $cypher .= sprintf('%s %s.%s = {%s} ', $clause, $identifier, $key, $key);
            $params[$key] = $criterion;
        }

        $cypher .= 'RETURN '.$identifier;

        return Statement::create($cypher, $params);
    }

    private function getSimpleRelationshipStatement($alias, $sourceEntity)
    {
        $relationshipMeta = $this->_classMetadata->getRelationship($alias);
        $relAlias = $relationshipMeta->getAlias();
        $targetAlias = $this->_em->getClassMetadataFor($relationshipMeta->getTargetEntity())->getEntityAlias();
        $sourceEntityId = $this->_classMetadata->getIdValue($sourceEntity);
        $relationshipType = $relationshipMeta->getType();

        $isIncoming = $relationshipMeta->getDirection() === DirectionUtils::INCOMING ? '<' : '';
        $isOutgoing = $relationshipMeta->getDirection() === DirectionUtils::OUTGOING ? '>' : '';

        $relPattern = sprintf('%s-[%s:`%s`]-%s', $isIncoming, $relAlias, $relationshipType, $isOutgoing);

        $cypher  = 'MATCH (n) WHERE id(n) = {id} ';
        $cypher .= 'MATCH (n)'.$relPattern.'('.$targetAlias.') ';
        $cypher .= 'RETURN '.$targetAlias;

        $params = ['id' => (int) $sourceEntityId];

        return Statement::create($cypher, $params);
    }

    private function getSimpleRelationshipCollectionStatement($alias, $sourceEntity)
    {
        $relationshipMeta = $this->_classMetadata->getRelationship($alias);
        $relAlias = $relationshipMeta->getAlias();
        $targetAlias = $this->_em->getClassMetadataFor($relationshipMeta->getTargetEntity())->getEntityAlias();
        $sourceEntityId = $this->_classMetadata->getIdValue($sourceEntity);
        $relationshipType = $relationshipMeta->getType();

        $isIncoming = $relationshipMeta->getDirection() === DirectionUtils::INCOMING ? '<' : '';
        $isOutgoing = $relationshipMeta->getDirection() === DirectionUtils::OUTGOING ? '>' : '';

        $relPattern = sprintf('%s-[%s:`%s`]-%s', $isIncoming, $relAlias, $relationshipType, $isOutgoing);

        $cypher  = 'MATCH (n) WHERE id(n) = {id} ';
        $cypher .= 'MATCH (n)'.$relPattern.'('.$targetAlias.') ';
        $cypher .= 'RETURN collect('.$targetAlias.') AS '.$targetAlias;

        $params = ['id' => $sourceEntityId];

        return Statement::create($cypher, $params);
    }
}