<?php
namespace Data;

/**
 * Choose in which collection must go an unwinded data.
 *
 * @author zuri
 * 
 */
interface IDataLocation
{

    function writeData(array $unwindPath, array $data): void;

    function collectionJsonFiles(): array;

    function getDBCollections(): array;
}