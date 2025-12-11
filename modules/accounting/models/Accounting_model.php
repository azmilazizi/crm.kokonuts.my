                $node['addedfrom'] = $addedFrom;
                $node['addedfrom'] = $addedFrom;
                $node['addedfrom'] = $addedFrom;
        $addedFrom = isset($data['created_by']) ? (int) $data['created_by'] : get_staff_user_id();

        if (isset($data['created_by'])) {
            unset($data['created_by']);
        }

        $data['addedfrom'] = $addedFrom;
                $node['addedfrom'] = $addedFrom;
