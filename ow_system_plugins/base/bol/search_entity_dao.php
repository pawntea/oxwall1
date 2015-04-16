<?php

/**
 * EXHIBIT A. Common Public Attribution License Version 1.0
 * The contents of this file are subject to the Common Public Attribution License Version 1.0 (the “License”);
 * you may not use this file except in compliance with the License. You may obtain a copy of the License at
 * http://www.oxwall.org/license. The License is based on the Mozilla Public License Version 1.1
 * but Sections 14 and 15 have been added to cover use of software over a computer network and provide for
 * limited attribution for the Original Developer. In addition, Exhibit A has been modified to be consistent
 * with Exhibit B. Software distributed under the License is distributed on an “AS IS” basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for the specific language
 * governing rights and limitations under the License. The Original Code is Oxwall software.
 * The Initial Developer of the Original Code is Oxwall Foundation (http://www.oxwall.org/foundation).
 * All portions of the code written by Oxwall Foundation are Copyright (c) 2011. All Rights Reserved.

 * EXHIBIT B. Attribution Information
 * Attribution Copyright Notice: Copyright 2011 Oxwall Foundation. All rights reserved.
 * Attribution Phrase (not exceeding 10 words): Powered by Oxwall community software
 * Attribution URL: http://www.oxwall.org/
 * Graphic Image as provided in the Covered Code.
 * Display of Attribution Information is required in Larger Works which are defined in the CPAL as a work
 * which combines Covered Code or portions thereof with code not governed by the terms of the CPAL.
 */

/**
 * Data Access Object for `base_search_entity` table.
 * 
 * @author Alex Ermashev <alexermashev@gmail.com>
 * @package ow_system_plugins.base.bol
 * @since 1.0
 */
class BOL_SearchEntityDao extends OW_BaseDao
{
    /**
     * Entity type
     */
    const ENTITY_TYPE = 'entityType';

    /**
     * Entity id
     */
    const ENTITY_ID = 'entityId';

    /**
     * Text
     */
    const TEXT = 'text';

    /**
     * Status
     */
    const STATUS = 'status';

    /**
     * Timestamp
     */
    const TIMESTAMP = 'timeStamp';

    /**
     * Entity active status
     */
    const ENTITY_STATUS_ACTIVE = 1;

    /**
     * Entity not active status
     */
    const ENTITY_STATUS_NOT_ACTIVE = 0;

    /**
     * Sort by date
     */
    CONST SORT_BY_DATE = 'date';

    /**
     * Sort by relevance
     */
    CONST SORT_BY_RELEVANCE = 'relevance';

    /**
     * Singleton instance.
     *
     * @var BOL_SearchEntityDao
     */
    private static $classInstance;

    /**
     * Full text search in boolean mode
     * @var boolean
     */
    private $fullTextSearchBooleanMode = true;

    /**
     * Returns an instance of class (singleton pattern implementation).
     *
     * @return BOL_SearchEntityDao
     */
    public static function getInstance()
    {
        if ( self::$classInstance === null )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    /**
     * Constructor.
     */
    protected function __construct()
    {
        parent::__construct();
    }

    /**
     * @see OW_BaseDao::getDtoClassName()
     *
     */
    public function getDtoClassName()
    {
        return 'BOL_SearchEntity';
    }

    /**
     * @see OW_BaseDao::getTableName()
     *
     */
    public function getTableName()
    {
        return OW_DB_PREFIX . 'base_search_entity';
    }

    /**
     * Finds entity parts
     *
     * @param string $entityType
     * @param int $entityId
     * @return OW_Entity
     */
    public function findEntityParts( $entityType, $entityId = null)
    {
        $example = new OW_Example();
        $example->andFieldEqual(self::ENTITY_TYPE, $entityType);

        if ( $entityId )
        {
            $example->andFieldEqual(self::ENTITY_ID, $entityId);
        }

        return $this->findListByExample($example);
    }

    /**
     * Finds all entities
     *
     * @param integer $first
     * @param integer $limit 
     * @param string $entityType 
     * @return array
     */
    public function findAllEntities( $first, $limit, $entityType = null )
    {
        $params = array();
        $sql = 'SELECT * FROM ' . $this->getTableName() . ' WHERE 1';

        if ( $entityType ) 
        {
            $sql .=  ' AND `' . self::ENTITY_TYPE . '` = ? ';
            $params = array_merge($params, array(
                $entityType
            ));
        }

        $params = array_merge($params, array(
            $first,
            $limit
        ));

        $sql .= ' LIMIT ?, ?';

        return $this->dbo->queryForList($sql, $params);
    }

    /**
     * Delete all entities
     * 
     * @return void
     */
    public function deleteAllEntities()
    {
        $this->dbo->delete('TRUNCATE TABLE ' . $this->getTableName());
    }

    /**
     * Set entities status
     * 
     * @param string $entityType
     * @param integer $status
     * @param integer $entityId
     * @return void
     */
    public function setEntitiesStatus( $entityType = null, $status = self::ENTITY_STATUS_ACTIVE, $entityId = null )
    {
        $params = array(
            ($status == self::ENTITY_STATUS_ACTIVE ? self::ENTITY_STATUS_ACTIVE : self::ENTITY_STATUS_NOT_ACTIVE)
        );

        $sql = 'UPDATE `' . $this->getTableName() . '` SET `' . self::STATUS . '` = ? WHERE 1';

        if ( $entityType ) 
        {
            $sql .=  ' AND `' . self::ENTITY_TYPE . '` = ? ';
            $params = array_merge($params, array(
                $entityType
            ));
        }

        if ( $entityId ) 
        {
            $sql .=  ' AND `' . self::ENTITY_ID . '` = ? ';
            $params = array_merge($params, array(
                $entityId
            ));
        }

        $this->dbo->query($sql, $params);
    }

    /**
     * Find entities count by text
     * 
     * @param string $text
     * @param array $tags
     * @return integer
     */
    public function findEntitiesCountByText(  $text, array $tags = array() )
    {
        // sql params
        $queryParams = array(
            ':search' => $text,
            ':status' => self::ENTITY_STATUS_ACTIVE
        );

        // search without tags
        if ( !$tags ) 
        {
            $subQuery = '
                SELECT 
                    b.' . self::ENTITY_TYPE . ', 
                    b.' . self::ENTITY_ID . ' 
                FROM 
                    ' . $this->getTableName() . ' b
                WHERE 
                    MATCH (b.' . self::TEXT . ') AGAINST (:search ' . $this->getFullTextSearchMode() . ')
                        AND 
                    b.'. self::STATUS  . ' = :status';
        }
        else
        {
            $enityTags = BOL_SearchEntityTagDao::getInstance();

            $subQuery = '
                SELECT 
                    b.' . self::ENTITY_TYPE . ', 
                    b.' . self::ENTITY_ID . ' 
                FROM 
                    ' . $enityTags->getTableName() . ' a
                INNER JOIN
                    ' . $this->getTableName() . ' b
                ON
                    a.' . BOL_SearchEntityTagDao::ENTITY_SEARCH_ID . ' = b.id 
                        AND 
                    MATCH (b.' . self::TEXT . ') AGAINST (:search ' . $this->getFullTextSearchMode() . ')
                       AND 
                    b.'. self::STATUS  . ' = :status
                WHERE 
                    a.' . BOL_SearchEntityTagDao::ENTITY_TAG . ' IN (' . $this->dbo->mergeInClause($tags) . ')';
        }

        // build main query
        $query = '
            SELECT
                COUNT(*) as rowsCount
            FROM (
                SELECT 
                    DISTINCT ' .  
                    self::ENTITY_TYPE . ', ' . 
                    self::ENTITY_ID . '
                FROM 
                    (' . $subQuery . ') result
            )as rows';

        $result = $this->dbo->queryForRow($query, $queryParams);
        return !empty($result['rowsCount']) ? $result['rowsCount'] : 0;
    }

    /**
     * Find entities by text
     * 
     * @param string $text
     * @param integer $first
     * @param integer $limit
     * @param array $tags
     * @param string $sort
     * @return array
     */
    public function findEntitiesByText(  $text, $first, $limit, array $tags = array(), $sort = self::SORT_BY_RELEVANCE )
    {
        // sql params
        $queryParams = array(
            ':search' => $text,
            ':first' => $first,
            ':limit' => $limit,
            ':status' => self::ENTITY_STATUS_ACTIVE
        );

        // search without tags
        if ( !$tags ) 
        {
            $subQuery = '
                SELECT 
                    b.' . self::ENTITY_TYPE . ', 
                    b.' . self::ENTITY_ID . ',
                    MATCH (b.' . self::TEXT . ') AGAINST (:search ' . $this->getFullTextSearchMode() . ') as relevance
                FROM 
                    ' . $this->getTableName() . ' b
                WHERE 
                    MATCH (b.' . self::TEXT . ') AGAINST (:search ' . $this->getFullTextSearchMode() . ')
                        AND 
                    b.'. self::STATUS  . ' = :status
                ORDER BY 
                    ' . ($sort == self::SORT_BY_DATE ? 'b.' . self::TIMESTAMP : 'relevance') . ' DESC';
        }
        else
        {
            $enityTags = BOL_SearchEntityTagDao::getInstance();

            $subQuery = '
                SELECT 
                    b.' . self::ENTITY_TYPE . ', 
                    b.' . self::ENTITY_ID . ',
                    MATCH (b.' . self::TEXT . ') AGAINST (:search ' . $this->getFullTextSearchMode() . ') as relevance
                FROM 
                    ' . $enityTags->getTableName() . ' a
                INNER JOIN
                    ' . $this->getTableName() . ' b
                ON
                    a.' . BOL_SearchEntityTagDao::ENTITY_SEARCH_ID . ' = b.id 
                        AND 
                    MATCH (b.' . self::TEXT . ') AGAINST (:search ' . $this->getFullTextSearchMode() . ')
                       AND 
                    b.'. self::STATUS  . ' = :status
                WHERE 
                    a.' . BOL_SearchEntityTagDao::ENTITY_TAG . ' IN (' . $this->dbo->mergeInClause($tags) . ')
                ORDER BY 
                    ' . ($sort == self::SORT_BY_DATE ? 'b.' . self::TIMESTAMP : 'relevance') . ' DESC';
        }

        // build main query
        $query = '
            SELECT 
                DISTINCT ' .  
                self::ENTITY_TYPE . ', ' . 
                self::ENTITY_ID . '
            FROM 
                (' . $subQuery . ') result
            LIMIT 
                :first, 
                :limit';

        return $this->dbo->queryForList($query, $queryParams);
    }

    /**
     * Get full text search mode
     * 
     * @return string
     */
    protected function getFullTextSearchMode()
    {
        return $this->fullTextSearchBooleanMode ? 'IN BOOLEAN MODE' : 'IN NATURAL LANGUAGE MODE';
    }
}