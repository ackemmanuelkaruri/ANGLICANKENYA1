<?php
/**
 * ============================================
 * ANALYTICS QUERY EXECUTION ENGINE
 * Maps query types to actual SQL queries
 * ============================================
 */

function execute_analytics_query($query_type, $selected_scope_level, $selected_scope_id, $pdo, $comparison_period = 'none') {
    
    $result = [
        'success' => false,
        'data' => [],
        'chart_type' => 'bar',
        'error' => null
    ];
    
    // Build scope filter based on database structure
    $scope_filter = '';
    $scope_params = [];
    
    if ($selected_scope_level !== 'global' && $selected_scope_id > 0) {
        switch($selected_scope_level) {
            case 'parish':
                $scope_filter = " AND u.parish_id = ?";
                $scope_params[] = $selected_scope_id;
                break;
            case 'deanery':
                $scope_filter = " AND u.deanery_id = ?";
                $scope_params[] = $selected_scope_id;
                break;
            case 'archdeaconry':
                $scope_filter = " AND u.archdeaconry_id = ?";
                $scope_params[] = $selected_scope_id;
                break;
            case 'diocese':
                $scope_filter = " AND u.diocese_id = ?";
                $scope_params[] = $selected_scope_id;
                break;
        }
    }
    
    try {
        switch($query_type) {
            
            // ============================================
            // 1. CORE DEMOGRAPHICS
            // ============================================
            
            case 'age_gender_pyramid':
                $sql = "SELECT 
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 12 THEN '0-12'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 17 THEN '13-17'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 25 THEN '18-25'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 26 AND 35 THEN '26-35'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 36 AND 45 THEN '36-45'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 46 AND 60 THEN '46-60'
                        ELSE '60+'
                    END AS age_group,
                    gender,
                    COUNT(*) as count
                FROM users u
                WHERE role_level = 'member' 
                AND date_of_birth IS NOT NULL
                {$scope_filter}
                GROUP BY age_group, gender
                ORDER BY age_group, gender";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'youth_segment':
                $sql = "SELECT 
                    'Youth (13-35)' as segment,
                    COUNT(*) as count
                FROM users u
                WHERE role_level = 'member'
                AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 35
                {$scope_filter}
                UNION ALL
                SELECT 
                    'Children (0-12)' as segment,
                    COUNT(*) as count
                FROM users u
                WHERE role_level = 'member'
                AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 12
                {$scope_filter}";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($scope_params, $scope_params));
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            case 'senior_segment':
                $sql = "SELECT 
                    '60-70' as age_range,
                    COUNT(*) as count
                FROM users u
                WHERE role_level = 'member'
                AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 60 AND 70
                {$scope_filter}
                UNION ALL
                SELECT 
                    '71-80' as age_range,
                    COUNT(*) as count
                FROM users u
                WHERE role_level = 'member'
                AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 71 AND 80
                {$scope_filter}
                UNION ALL
                SELECT 
                    '80+' as age_range,
                    COUNT(*) as count
                FROM users u
                WHERE role_level = 'member'
                AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) > 80
                {$scope_filter}";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($scope_params, $scope_params, $scope_params));
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'marital_status_split':
                $sql = "SELECT 
                    COALESCE(ud.marital_status, 'Unknown') as status,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN user_details ud ON u.id = ud.user_id
                WHERE u.role_level = 'member'
                {$scope_filter}
                GROUP BY ud.marital_status
                ORDER BY count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'doughnut';
                $result['success'] = true;
                break;
                
            case 'education_level_distribution':
                $sql = "SELECT 
                    COALESCE(ud.education_level, 'Not Specified') as education,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN user_details ud ON u.id = ud.user_id
                WHERE u.role_level = 'member'
                {$scope_filter}
                GROUP BY ud.education_level
                ORDER BY count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'occupation_status_chart':
                $sql = "SELECT 
                    COALESCE(ud.occupation, 'Not Specified') as occupation,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN user_details ud ON u.id = ud.user_id
                WHERE u.role_level = 'member'
                {$scope_filter}
                GROUP BY ud.occupation
                ORDER BY count DESC
                LIMIT 15";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'geographic_density_parish':
                $sql = "SELECT 
                    p.parish_name,
                    COUNT(u.id) as member_count
                FROM parishes p
                LEFT JOIN users u ON p.parish_id = u.parish_id AND u.role_level = 'member'
                WHERE 1=1 {$scope_filter}
                GROUP BY p.parish_id, p.parish_name
                ORDER BY member_count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'data_completeness_report':
                $sql = "SELECT 
                    CASE
                        WHEN (u.date_of_birth IS NOT NULL AND u.phone_number IS NOT NULL AND u.email IS NOT NULL AND ud.occupation IS NOT NULL AND ud.marital_status IS NOT NULL) THEN 'Complete (100%)'
                        WHEN (u.date_of_birth IS NOT NULL OR u.phone_number IS NOT NULL OR u.email IS NOT NULL OR ud.occupation IS NOT NULL) THEN 'Partial (50-99%)'
                        ELSE 'Incomplete (0-49%)'
                    END as completeness,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN user_details ud ON u.id = ud.user_id
                WHERE u.role_level = 'member'
                {$scope_filter}
                GROUP BY completeness
                ORDER BY count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            // ============================================
            // 2. SACRAMENTS & SPIRITUALITY
            // ============================================
            
            case 'baptism_status_count':
                $sql = "SELECT 
                    CASE WHEN sr.baptized = 'Yes' THEN 'Baptized' ELSE 'Not Baptized' END as status,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN sacrament_records sr ON u.id = sr.user_id
                WHERE u.role_level = 'member'
                {$scope_filter}
                GROUP BY status";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            case 'confirmation_status_count':
                $sql = "SELECT 
                    CASE WHEN sr.confirmed = 'Yes' THEN 'Confirmed' ELSE 'Not Confirmed' END as status,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN sacrament_records sr ON u.id = sr.user_id
                WHERE u.role_level = 'member'
                {$scope_filter}
                GROUP BY status";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            case 'sacrament_funnel_progress':
                $sql = "SELECT 
                    'Baptized' as stage,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN sacrament_records sr ON u.id = sr.user_id
                WHERE u.role_level = 'member' AND sr.baptized = 'Yes'
                {$scope_filter}
                UNION ALL
                SELECT 
                    'Baptized & Confirmed' as stage,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN sacrament_records sr ON u.id = sr.user_id
                WHERE u.role_level = 'member' AND sr.baptized = 'Yes' AND sr.confirmed = 'Yes'
                {$scope_filter}";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($scope_params, $scope_params));
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'certificate_holders':
                $sql = "SELECT 
                    'Has Both Certificates' as status,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN sacrament_records sr ON u.id = sr.user_id
                WHERE u.role_level = 'member' 
                AND sr.baptism_certificate IS NOT NULL 
                AND sr.confirmation_certificate IS NOT NULL
                {$scope_filter}
                UNION ALL
                SELECT 
                    'Missing Certificates' as status,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN sacrament_records sr ON u.id = sr.user_id
                WHERE u.role_level = 'member' 
                AND (sr.baptism_certificate IS NULL OR sr.confirmation_certificate IS NULL)
                {$scope_filter}";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($scope_params, $scope_params));
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'doughnut';
                $result['success'] = true;
                break;
                
            case 'need_baptism_followup':
                $sql = "SELECT 
                    'Want Baptism' as status,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN sacrament_records sr ON u.id = sr.user_id
                WHERE u.role_level = 'member' 
                AND (sr.baptized = 'No' OR sr.baptized IS NULL)
                AND sr.want_to_be_baptized = 'Yes'
                {$scope_filter}";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'need_confirmation_followup':
                $sql = "SELECT 
                    'Want Confirmation' as status,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN sacrament_records sr ON u.id = sr.user_id
                WHERE u.role_level = 'member' 
                AND (sr.confirmed = 'No' OR sr.confirmed IS NULL)
                AND sr.want_to_be_confirmed = 'Yes'
                {$scope_filter}";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'clergy_by_orders':
                $sql = "SELECT 
                    cr.role_name as role,
                    COUNT(*) as count
                FROM clergy_roles cr
                INNER JOIN users u ON cr.user_id = u.id
                WHERE cr.is_current = 1
                {$scope_filter}
                GROUP BY cr.role_name
                ORDER BY count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'clergy_active_inactive':
                $sql = "SELECT 
                    CASE WHEN cr.is_current = 1 THEN 'Active' ELSE 'Inactive' END as status,
                    COUNT(*) as count
                FROM clergy_roles cr
                INNER JOIN users u ON cr.user_id = u.id
                WHERE 1=1 {$scope_filter}
                GROUP BY status";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            case 'clergy_marital_status':
                $sql = "SELECT 
                    COALESCE(ud.marital_status, 'Unknown') as status,
                    COUNT(*) as count
                FROM clergy_roles cr
                INNER JOIN users u ON cr.user_id = u.id
                LEFT JOIN user_details ud ON u.id = ud.user_id
                WHERE cr.is_current = 1
                {$scope_filter}
                GROUP BY ud.marital_status
                ORDER BY count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'doughnut';
                $result['success'] = true;
                break;
                
            // ============================================
            // 3. MINISTRY PARTICIPATION
            // ============================================
            
            case 'ministry_participation_total':
                $sql = "SELECT 
                    m.ministry_department_name as ministry,
                    COUNT(DISTINCT m.user_id) as participant_count
                FROM ministries m
                INNER JOIN users u ON m.user_id = u.id
                WHERE m.user_id IS NOT NULL
                {$scope_filter}
                GROUP BY m.ministry_department_name
                ORDER BY participant_count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'participation_by_age':
                $sql = "SELECT 
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) < 18 THEN 'Under 18'
                        WHEN TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) BETWEEN 18 AND 35 THEN '18-35'
                        WHEN TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) BETWEEN 36 AND 50 THEN '36-50'
                        ELSE '50+'
                    END AS age_group,
                    COUNT(DISTINCT m.user_id) as count
                FROM ministries m
                INNER JOIN users u ON m.user_id = u.id
                WHERE m.user_id IS NOT NULL AND u.date_of_birth IS NOT NULL
                {$scope_filter}
                GROUP BY age_group
                ORDER BY age_group";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'multi_ministry_serving':
                $sql = "SELECT 
                    CASE 
                        WHEN ministry_count = 1 THEN '1 Ministry'
                        WHEN ministry_count = 2 THEN '2 Ministries'
                        WHEN ministry_count >= 3 THEN '3+ Ministries'
                    END as service_level,
                    COUNT(*) as member_count
                FROM (
                    SELECT user_id, COUNT(*) as ministry_count
                    FROM ministries m
                    INNER JOIN users u ON m.user_id = u.id
                    WHERE m.user_id IS NOT NULL
                    {$scope_filter}
                    GROUP BY user_id
                ) as ministry_counts
                GROUP BY service_level
                ORDER BY member_count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            case 'not_in_any_ministry':
                $sql1 = "SELECT COUNT(*) as count
                FROM users u
                WHERE u.role_level = 'member'
                AND u.id NOT IN (SELECT DISTINCT user_id FROM ministries WHERE user_id IS NOT NULL)
                {$scope_filter}";
                
                $sql2 = "SELECT COUNT(*) as count
                FROM users u
                WHERE u.role_level = 'member'
                AND u.id IN (SELECT DISTINCT user_id FROM ministries WHERE user_id IS NOT NULL)
                {$scope_filter}";
                
                $stmt1 = $pdo->prepare($sql1);
                $stmt1->execute($scope_params);
                $not_in = $stmt1->fetchColumn();
                
                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute($scope_params);
                $in_ministry = $stmt2->fetchColumn();
                
                $result['data'] = [
                    ['category' => 'Not in Ministry', 'count' => $not_in],
                    ['category' => 'In Ministry', 'count' => $in_ministry]
                ];
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            case 'ministry_gender_split':
                $sql = "SELECT 
                    m.ministry_department_name as ministry,
                    u.gender,
                    COUNT(*) as count
                FROM ministries m
                INNER JOIN users u ON m.user_id = u.id
                WHERE m.user_id IS NOT NULL AND u.gender IS NOT NULL
                {$scope_filter}
                GROUP BY m.ministry_department_name, u.gender
                ORDER BY m.ministry_department_name, u.gender";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'department_participation':
                $sql = "SELECT 
                    m.assignment_type as department_type,
                    COUNT(DISTINCT m.user_id) as participant_count
                FROM ministries m
                INNER JOIN users u ON m.user_id = u.id
                WHERE m.user_id IS NOT NULL
                {$scope_filter}
                GROUP BY m.assignment_type
                ORDER BY participant_count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'doughnut';
                $result['success'] = true;
                break;
                
            // ============================================
            // 4. GROUPS & FELLOWSHIPS
            // ============================================
            
            case 'cell_group_penetration':
                $sql = "SELECT 
                    'In Cell Groups' as status,
                    COUNT(DISTINCT mg.user_id) as count
                FROM member_groups mg
                INNER JOIN users u ON mg.user_id = u.id
                WHERE u.role_level = 'member' 
                AND mg.kikuyu_cell_group IS NOT NULL
                {$scope_filter}
                UNION ALL
                SELECT 
                    'Not in Cell Groups' as status,
                    COUNT(*) as count
                FROM users u
                WHERE u.role_level = 'member'
                AND u.id NOT IN (SELECT user_id FROM member_groups WHERE kikuyu_cell_group IS NOT NULL)
                {$scope_filter}";
                
                $stmt = $pdo->prepare($sql);
                $params = array_merge($scope_params, $scope_params);
                $stmt->execute($params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            case 'group_by_service_type':
                $sql = "SELECT 
                    COALESCE(mg.service_attending, 'Not Specified') as service_type,
                    COUNT(*) as count
                FROM member_groups mg
                INNER JOIN users u ON mg.user_id = u.id
                WHERE u.role_level = 'member'
                {$scope_filter}
                GROUP BY mg.service_attending
                ORDER BY count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'doughnut';
                $result['success'] = true;
                break;
                
            case 'members_by_cell_group':
                $sql = "SELECT 
                    mg.kikuyu_cell_group as cell_group,
                    COUNT(*) as member_count
           FROM member_groups mg
                INNER JOIN users u ON mg.user_id = u.id
                WHERE u.role_level = 'member' 
                AND mg.kikuyu_cell_group IS NOT NULL
                {$scope_filter}
                GROUP BY mg.kikuyu_cell_group
                ORDER BY member_count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'not_in_any_cell_group':
                $sql = "SELECT 
                    'In Cell Group' as status,
                    COUNT(*) as count
                FROM member_groups mg
                INNER JOIN users u ON mg.user_id = u.id
                WHERE u.role_level = 'member' AND mg.kikuyu_cell_group IS NOT NULL
                {$scope_filter}
                UNION ALL
                SELECT 
                    'Not in Cell Group' as status,
                    COUNT(*) as count
                FROM users u
                WHERE u.role_level = 'member'
                AND u.id NOT IN (SELECT user_id FROM member_groups WHERE kikuyu_cell_group IS NOT NULL)
                {$scope_filter}";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($scope_params, $scope_params));
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            // ============================================
            // 6. FAMILY & RELATIONSHIPS
            // ============================================
            
            case 'family_head_count':
                $sql = "SELECT 
                    p.parish_name,
                    COUNT(DISTINCT f.head_user_id) as family_heads
                FROM families f
                INNER JOIN users u ON f.head_user_id = u.id
                LEFT JOIN parishes p ON u.parish_id = p.parish_id
                WHERE 1=1 {$scope_filter}
                GROUP BY p.parish_id, p.parish_name
                ORDER BY family_heads DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'relationship_type_breakdown':
                $sql = "SELECT 
                    ur.relationship_type1 as relationship,
                    COUNT(*) as count
                FROM user_relationships ur
                INNER JOIN users u ON ur.user1_id = u.id
                WHERE ur.status = 'APPROVED'
                {$scope_filter}
                GROUP BY ur.relationship_type1
                ORDER BY count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'average_family_size':
                $sql = "SELECT 
                    CASE 
                        WHEN member_count = 1 THEN '1 Member'
                        WHEN member_count = 2 THEN '2 Members'
                        WHEN member_count BETWEEN 3 AND 4 THEN '3-4 Members'
                        WHEN member_count >= 5 THEN '5+ Members'
                    END as family_size,
                    COUNT(*) as family_count
                FROM (
                    SELECT f.family_id, COUNT(fm.id) as member_count
                    FROM families f
                    LEFT JOIN family_members fm ON f.family_id = fm.family_id
                    INNER JOIN users u ON f.head_user_id = u.id
                    WHERE 1=1 {$scope_filter}
                    GROUP BY f.family_id
                ) as family_sizes
                GROUP BY family_size
                ORDER BY family_count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'doughnut';
                $result['success'] = true;
                break;
                
            case 'members_with_dependents':
                $sql = "SELECT 
                    'Has Dependents' as status,
                    COUNT(DISTINCT d.parent_user_id) as count
                FROM dependents d
                INNER JOIN users u ON d.parent_user_id = u.id
                WHERE u.role_level = 'member'
                AND TIMESTAMPDIFF(YEAR, d.date_of_birth, CURDATE()) < 18
                {$scope_filter}
                UNION ALL
                SELECT 
                    'No Dependents' as status,
                    COUNT(*) as count
                FROM users u
                WHERE u.role_level = 'member'
                AND u.id NOT IN (
                    SELECT DISTINCT parent_user_id FROM dependents 
                    WHERE TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18
                )
                {$scope_filter}";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($scope_params, $scope_params));
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            case 'dependents_in_school':
                $sql = "SELECT 
                    CASE 
                        WHEN d.school_name IS NOT NULL THEN 'In School'
                        ELSE 'Not in School'
                    END as school_status,
                    COUNT(*) as count
                FROM dependents d
                INNER JOIN users u ON d.parent_user_id = u.id
                WHERE u.role_level = 'member'
                {$scope_filter}
                GROUP BY school_status";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            case 'dependents_age_distribution':
                $sql = "SELECT 
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, d.date_of_birth, CURDATE()) BETWEEN 0 AND 5 THEN '0-5'
                        WHEN TIMESTAMPDIFF(YEAR, d.date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN '6-12'
                        WHEN TIMESTAMPDIFF(YEAR, d.date_of_birth, CURDATE()) BETWEEN 13 AND 17 THEN '13-17'
                        ELSE '18+'
                    END AS age_group,
                    COUNT(*) as count
                FROM dependents d
                INNER JOIN users u ON d.parent_user_id = u.id
                WHERE u.role_level = 'member' AND d.date_of_birth IS NOT NULL
                {$scope_filter}
                GROUP BY age_group
                ORDER BY age_group";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            // ============================================
            // 7. PASTORAL & VISITORS
            // ============================================
            
            case 'visitor_conversion_rate':
                $sql = "SELECT 
                    CASE 
                        WHEN v.converted_to_member = 1 THEN 'Converted to Members'
                        ELSE 'Still Visitors'
                    END as status,
                    COUNT(*) as count
                FROM visitors v
                INNER JOIN parishes p ON v.parish_id = p.parish_id
                LEFT JOIN users u ON p.parish_id = u.parish_id
                WHERE 1=1 {$scope_filter}
                GROUP BY status";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            case 'visitor_followup_status':
                $sql = "SELECT 
                    v.follow_up_status as status,
                    COUNT(*) as count
                FROM visitors v
                INNER JOIN parishes p ON v.parish_id = p.parish_id
                LEFT JOIN users u ON p.parish_id = u.parish_id
                WHERE 1=1 {$scope_filter}
                GROUP BY v.follow_up_status
                ORDER BY count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'visitor_retention_trend':
                $sql = "SELECT 
                    v.visitor_type,
                    COUNT(*) as count
                FROM visitors v
                INNER JOIN parishes p ON v.parish_id = p.parish_id
                LEFT JOIN users u ON p.parish_id = u.parish_id
                WHERE 1=1 {$scope_filter}
                GROUP BY v.visitor_type
                ORDER BY count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'visitor_source_breakdown':
                $sql = "SELECT 
                    COALESCE(v.how_heard, 'Not Specified') as source,
                    COUNT(*) as count
                FROM visitors v
                INNER JOIN parishes p ON v.parish_id = p.parish_id
                LEFT JOIN users u ON p.parish_id = u.parish_id
                WHERE 1=1 {$scope_filter}
                GROUP BY v.how_heard
                ORDER BY count DESC
                LIMIT 10";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'unassigned_followup_list':
                $sql = "SELECT 
                    'Assigned' as status,
                    COUNT(*) as count
                FROM visitors v
                INNER JOIN parishes p ON v.parish_id = p.parish_id
                LEFT JOIN users u ON p.parish_id = u.parish_id
                WHERE v.follow_up_assigned_to IS NOT NULL
                {$scope_filter}
                UNION ALL
                SELECT 
                    'Unassigned' as status,
                    COUNT(*) as count
                FROM visitors v
                INNER JOIN parishes p ON v.parish_id = p.parish_id
                LEFT JOIN users u ON p.parish_id = u.parish_id
                WHERE v.follow_up_assigned_to IS NULL
                {$scope_filter}";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($scope_params, $scope_params));
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            // ============================================
            // 8. EVENTS & ATTENDANCE
            // ============================================
            
            case 'events_volume_by_type':
                $sql = "SELECT 
                    COALESCE(e.created_by_role, 'Not Specified') as event_type,
                    COUNT(*) as count
                FROM events e
                WHERE 1=1 {$scope_filter}
                GROUP BY e.created_by_role
                ORDER BY count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'monthly_service_attendance':
                $sql = "SELECT 
                    DATE_FORMAT(ss.service_date, '%Y-%m') as month,
                    AVG(ss.actual_attendance) as avg_attendance
                FROM service_schedule ss
                INNER JOIN parishes p ON ss.parish_id = p.parish_id
                LEFT JOIN users u ON p.parish_id = u.parish_id
                WHERE ss.actual_attendance IS NOT NULL
                AND ss.service_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                {$scope_filter}
                GROUP BY month
                ORDER BY month";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'line';
                $result['success'] = true;
                break;
                
            // ============================================
            // 9. SYSTEM & SECURITY
            // ============================================
            
            case 'active_vs_inactive_users':
                $sql = "SELECT 
                    u.account_status as status,
                    COUNT(*) as count
                FROM users u
                WHERE 1=1 {$scope_filter}
                GROUP BY u.account_status
                ORDER BY count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'doughnut';
                $result['success'] = true;
                break;
                
            case 'audit_log_volume_trend':
                $sql = "SELECT 
                    DATE_FORMAT(al.change_timestamp, '%Y-%m') as month,
                    COUNT(*) as log_count
                FROM audit_log al
                WHERE al.change_timestamp >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY month
                ORDER BY month";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'line';
                $result['success'] = true;
                break;
                
            case 'data_changes_by_admin':
                $sql = "SELECT 
                    al.changed_by as admin,
                    COUNT(*) as change_count
                FROM audit_log al
                WHERE al.change_timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY al.changed_by
                ORDER BY change_count DESC
                LIMIT 15";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'user_access_log':
                $sql = "SELECT 
                    u.role_level,
                    COUNT(DISTINCT u.id) as user_count,
                    MAX(u.last_login) as last_access
                FROM users u
                WHERE 1=1 {$scope_filter}
                GROUP BY u.role_level
                ORDER BY user_count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
            case 'unverified_member_accounts':
                $sql = "SELECT 
                    CASE WHEN u.email_verified = 1 THEN 'Verified' ELSE 'Unverified' END as status,
                    COUNT(*) as count
                FROM users u
                WHERE u.role_level = 'member'
                {$scope_filter}
                GROUP BY status";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            case 'roles_by_diocese_arch':
                $sql = "SELECT 
                    u.role_level,
                    COUNT(*) as count
                FROM users u
                WHERE 1=1 {$scope_filter}
                GROUP BY u.role_level
                ORDER BY count DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($scope_params);
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'bar';
                $result['success'] = true;
                break;
                
          case 'log_in_attempts_failures':
                $sql = "SELECT 
                    CASE WHEN la.was_successful = 1 THEN 'Success' ELSE 'Failed' END as status,
                    COUNT(*) as count
                FROM login_attempts la
                WHERE la.attempt_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY status";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $result['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result['chart_type'] = 'pie';
                $result['success'] = true;
                break;
                
            // ============================================
            // DEFAULT CASE
            // ============================================
            
            default:
                $result['error'] = "Query type '{$query_type}' not yet implemented. Please contact support to add this query.";
                break;
        }
        
    } catch (PDOException $e) {
        $result['error'] = "Database error: " . $e->getMessage();
        error_log("Analytics Query Error [{$query_type}]: " . $e->getMessage());
    }
    
    return $result;
}
?>
```
