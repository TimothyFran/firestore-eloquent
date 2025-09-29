<?php

namespace Roddy\FirestoreEloquent\Firestore\Eloquent\Traits;

use Roddy\FirestoreEloquent\Firestore\Eloquent\DataHelperController;

trait DataParser
{
    /**
     * Extract document ID from Firestore document name.
     * Format: projects/{projectId}/databases/{databaseId}/documents/{document_path}
     * Returns just the last segment which is the actual document ID.
     */
    private function extractDocumentName($firestoreDocumentName)
    {
        if (empty($firestoreDocumentName)) {
            return null;
        }
        
        $segments = explode('/', $firestoreDocumentName);
        return end($segments);
    }

    private function parseFirestoreJson($json)
    {
        $extractValue = function ($value) use (&$extractValue, &$parseFields) {
            if (isset($value['stringValue'])) return $value['stringValue'];
            if (isset($value['booleanValue'])) return $value['booleanValue'];
            if (isset($value['integerValue'])) return (int) $value['integerValue'];
            if (isset($value['doubleValue'])) return (float) $value['doubleValue'];
            if (isset($value['timestampValue'])) return $value['timestampValue'];
            if (isset($value['mapValue']['fields'])) return $parseFields($value['mapValue']['fields'], $extractValue);
            if (isset($value['arrayValue']['values'])) {
                return array_map(function ($item) use ($extractValue) {
                    return $extractValue($item);
                }, $value['arrayValue']['values']);
            }
            return null;
        };

        $parseFields = function ($fields, $extractValue) {
            $parsed = [];
            foreach ($fields as $key => $value) {
                $parsed[$key] = $extractValue($value);
            }
            return $parsed;
        };

        $data = $json;
        $result = [];

        $convertFunction  = function ($data) {
            return $this->convertToFirestoreFormat($data);
        };
        $patchRequestFuntion = function ($data, $id) {
            return $this->patchRequest($this->collection, $data, true, false, $id);
        };

        $deleteRequestFuntion = function ($id) {
            return $this->deleteRequest($this->collection, true, false, $id);
        };
        if (isset($data['documents'])) {
            foreach ($data['documents'] as $doc) {
                $parsedData = $parseFields($doc['fields'], $extractValue);
                if ($this->hidden) {
                    $parsedData = array_diff_key($parsedData, array_flip($this->hidden));
                }
                // Extract the full document name (Firestore document ID)
                $firestoreDocumentName = $doc['name'] ?? null;
                $result[] = new DataHelperController($parsedData, $convertFunction, $this->primaryKey, $this->collection, $patchRequestFuntion, $deleteRequestFuntion, $this->modelClass, $firestoreDocumentName);
            }
        } elseif (isset($data[0]['document'])) {
            foreach ($data as $doc) {
                $parsedData = $parseFields($doc['document']['fields'], $extractValue);
                if ($this->hidden) {
                    $parsedData = array_diff_key($parsedData, array_flip($this->hidden));
                }
                // Extract the full document name (Firestore document ID)
                $firestoreDocumentName = $doc['document']['name'] ?? null;
                $result[] = new DataHelperController($parsedData, $convertFunction, $this->primaryKey, $this->collection, $patchRequestFuntion, $deleteRequestFuntion, $this->modelClass, $firestoreDocumentName);
            }
        }

        return $result;
    }
}
