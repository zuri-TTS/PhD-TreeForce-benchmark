<?php
namespace Data;

final class OneCollectionLocation implements IDataLocation
{

    private string $collection;

    public function __construct(string $collection)
    {
        $this->collection = $collection;
    }

    public function writeData(array $unwindPath, array $data): void
    {
        throw new \Error("Invalid operation");
    }

    public function collectionJsonFiles(): array
    {
        throw new \Error("Invalid operation");
    }

    public function getDBCollections(): array
    {
        return (array) $this->collection;
    }
}