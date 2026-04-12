<?php

declare(strict_types=1);

/**
 * Class ilCloudStorageTreeGUI
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */

class ilCloudStorageTreeGUI extends ilCloudStorageTreeExplorerLegacyGUI
{
    public function __construct(string $a_expl_id, ilObjCloudStorageGUI $a_parent_obj, string $a_parent_cmd, ilCloudStorageTree $tree)
    {
        global $tpl, $ilLog;
        parent::__construct($a_expl_id, $a_parent_obj, $a_parent_cmd, $tree);
        $this->setSkipRootNode(false);
        $this->setPreloadChilds(false);
        $this->setAjax(true);

        // Prevent LegacyGUI::getRootNode() from calling getTree()->getNodeData()
        // which does not exist on ilCloudStorageTree. We set a dummy array so
        // the isset() check passes and our overridden getRootNode() is used instead.
        $this->root_node_data = ['_dummy' => true];

        // necessary from 5.4 to fix bug where only root node shows
        $this->setNodeOpen($this->getNodeId($this->getRootNode()));

        $this->log = $ilLog;
        $css = '.jstree a.clickable_node {
               color:black !important;
             }

             .jstree a:hover {
               color:#b2052e !important;
             }';
        $tpl->addInlineCss($css);
        $container_outer_id = "il_expl2_jstree_cont_out_" . $this->getId();
        $tpl->addOnLoadCode('$("#' . $container_outer_id . '").removeClass("ilNoDisplay");');
    }

    public function getNodeIcon($a_node): string
    {
        if ($a_node->getType() == ilCloudStorageItem::TYPE_FILE) {
            $img = 'icon_dcl_file.svg';
        } else {
            $img = 'icon_dcl_fold.svg';
        }
        return ilObjCloudStorageGUI::getImagePath($img);
    }

    public function getNodeIconAlt($a_node): string
    {
        return '';
    }

    public function getNodeContent($node): string
    {
        assert($this->parent_obj instanceof ilObjCloudStorageGUI);
        $name = $node->getName();
        return htmlspecialchars($name ?: $this->parent_obj->getRootName());
    }

    public function getNodeHref($node): string
    {
        global $ilCtrl;
        $ilCtrl->setParameter($this->parent_obj, 'root_path', $this->urlencode($node->getFullPath()));
        return $ilCtrl->getLinkTarget($this->parent_obj, 'editProperties');
    }

    protected function urlencode(string $str): string
    {
        return str_replace('%2F', '/', rawurlencode($str));
    }

    public function isNodeClickable($node): bool
    {
        return ($node->getType() == ilCloudStorageItem::TYPE_FOLDER);
    }

    /**
     * Always treat folders as expandable, even if empty.
     * Without this, the ILIAS core calls getChildsOfNode() per node to check
     * for children, rendering empty folders as jstree-leaf (no expand arrow).
     */
    public function isNodeHasChilds($node): bool
    {
        return ($node->getType() == ilCloudStorageItem::TYPE_FOLDER);
    }

    public function getRootNode(): ilCloudStorageFolder
    {
        assert($this->tree instanceof ilCloudStorageTree);
        return $this->tree->getRootNode();
    }

    public function getNodeId($a_node): string
    {
        return ilCloudStorageUtil::encodeBase64Path($a_node->getFullPath());
    }

    /**
     * Override getChildren to work with ilCloudStorageItem objects instead of arrays.
     */
    public function getChildren($record, $environment = null): array
    {
        // Never try to list children of a file – the Graph API returns 422.
        if ($record->getType() === ilCloudStorageItem::TYPE_FILE) {
            return [];
        }
        return $this->getChildsOfNode($this->getNodeId($record));
    }



    /**
     * Override toggleExplorerNodeState: ILIAS base class casts node_id to (int)
     * which destroys Base64 strings. We keep it as string.
     */
    public function toggleExplorerNodeState(): void
    {
        $nodeId = $this->httpRequest->getQueryParams()[$this->node_parameter_name] ?? '';
        $priorState = (int) ($this->httpRequest->getQueryParams()['prior_state'] ?? 0);

        if ($nodeId !== '') {
            if (0 === $priorState && !in_array($nodeId, $this->open_nodes, true)) {
                $this->open_nodes[] = $nodeId;
            } elseif (1 === $priorState && in_array($nodeId, $this->open_nodes, true)) {
                $key = array_search($nodeId, $this->open_nodes, true);
                unset($this->open_nodes[$key]);
            }
            $this->store->set('on_' . $this->id, serialize($this->open_nodes));
        }
        exit();
    }
}