<?php

declare(strict_types=1);

namespace App\Notes\Presentation\Form;

use App\Notes\Domain\Entity\Folder;

final class FolderChoiceTree
{
    /**
     * @param list<Folder> $folders
     *
     * @return list<Folder>
     */
    public static function arrange(array $folders): array
    {
        $available = [];
        foreach ($folders as $folder) {
            $available[spl_object_id($folder)] = true;
        }

        $roots = [];
        $children = [];
        foreach ($folders as $folder) {
            $parent = $folder->getParent();
            if (null === $parent || !isset($available[spl_object_id($parent)])) {
                $roots[] = $folder;
                continue;
            }

            $children[spl_object_id($parent)][] = $folder;
        }

        $arranged = [];
        $visited = [];
        $appendBranch = static function (Folder $folder) use (&$appendBranch, &$arranged, &$visited, $children): void {
            $objectId = spl_object_id($folder);
            if (isset($visited[$objectId])) {
                return;
            }

            $visited[$objectId] = true;
            $arranged[] = $folder;

            foreach ($children[$objectId] ?? [] as $child) {
                $appendBranch($child);
            }
        };

        foreach ($roots as $root) {
            $appendBranch($root);
        }

        // Keep malformed cyclic data selectable instead of silently dropping it.
        foreach ($folders as $folder) {
            $appendBranch($folder);
        }

        return $arranged;
    }

    public static function label(Folder $folder): string
    {
        $depth = 0;
        $parent = $folder->getParent();
        $visited = [spl_object_id($folder) => true];

        while (null !== $parent && !isset($visited[spl_object_id($parent)])) {
            $visited[spl_object_id($parent)] = true;
            ++$depth;
            $parent = $parent->getParent();
        }

        if (0 === $depth) {
            return $folder->getName();
        }

        return str_repeat("\u{00A0}\u{00A0}\u{00A0}", $depth).'↳ '.$folder->getName();
    }
}
