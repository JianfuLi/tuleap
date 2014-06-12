<?php
/**
 * Copyright (c) Xerox Corporation, CodeX Team, 2001-2009. All rights reserved
 *
 * This file is a part of CodeX.
 *
 * CodeX is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * CodeX is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */

class ArtifactDao extends DataAccessObject {
    public function __construct($da = null) {
        parent::__construct($da);
        $this->table_name = 'artifact';
    }

    public function searchArtifactId($artifact_id){
        $artifact_id= $this->da->quoteSmart($artifact_id);
        $sql = "SELECT group_id 
                FROM $this->table_name, artifact_group_list
                WHERE artifact.group_artifact_id=artifact_group_list.group_artifact_id 
                    AND artifact.artifact_id=$artifact_id";
        return $this->retrieve($sql);
    }

    public function searchGlobal($words, $exact, $offset, $atid, array $user_ugroups) {
        if ($exact) {
            $details = $this->searchExactMatch($words);
            $summary = $this->searchExactMatch($words);
            $history = $this->searchExactMatch($words);
        } else {
            $details = $this->searchExplodeMatch('artifact.details', $words);
            $summary = $this->searchExplodeMatch('artifact.summary', $words);
            $history = $this->searchExplodeMatch('artifact_history.new_value', $words);
        }
        $offset       = $this->da->escapeInt($offset);
        $atid         = $this->da->escapeInt($atid);
        $user_ugroups = $this->da->escapeIntImplode($user_ugroups);

        $sql = "SELECT SQL_CALC_FOUND_ROWS artifact.artifact_id,
                   artifact.summary,
                   artifact.open_date,
                   user.user_name
                FROM artifact INNER JOIN user ON user.user_id=artifact.submitted_by
                   LEFT JOIN artifact_history ON artifact_history.artifact_id=artifact.artifact_id
                   LEFT JOIN permissions ON (permissions.object_id = CAST(artifact.artifact_id AS CHAR) AND permissions.permission_type = 'TRACKER_ARTIFACT_ACCESS')
                WHERE artifact.group_artifact_id=$atid
                  AND (
                        artifact.use_artifact_permissions = 0
                        OR
                        (
                            permissions.ugroup_id IN ($user_ugroups)
                        )
                  )
                  AND (
                        (artifact.details LIKE $details)
                        OR
                        (artifact.summary LIKE $summary)
                        OR
                        (artifact_history.field_name='comment' AND (artifact_history.new_value LIKE $history))
                  )
                GROUP BY open_date DESC
                LIMIT $offset, 25";
        return $this->retrieve($sql);
    }
}
