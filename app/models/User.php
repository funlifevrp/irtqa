<?php
/**
 * نموذج المستخدم
 * Halqat Management System
 */

class User
{
    private $db;
    private $table = 'users';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * إنشاء مستخدم جديد
     */
    public function create($data)
    {
        try {
            // التحقق من صحة البيانات
            $validation = $this->validateUserData($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'بيانات غير صحيحة',
                    'errors' => $validation['errors']
                ];
            }

            // التحقق من عدم تكرار اسم المستخدم
            if (!empty($data['username']) && $this->usernameExists($data['username'])) {
                return [
                    'success' => false,
                    'message' => 'اسم المستخدم موجود مسبقاً'
                ];
            }

            // التحقق من عدم تكرار الرمز الشخصي
            if (!empty($data['personal_code']) && $this->personalCodeExists($data['personal_code'])) {
                return [
                    'success' => false,
                    'message' => 'الرمز الشخصي موجود مسبقاً'
                ];
            }

            // تشفير كلمة المرور إذا كانت موجودة
            if (!empty($data['password'])) {
                $data['password'] = Security::hashPassword($data['password']);
            }

            // إعداد البيانات للإدراج
            $insertData = [
                'full_name' => Security::sanitizeInput($data['full_name']),
                'role' => Security::sanitizeInput($data['role']),
                'is_active' => $data['is_active'] ?? 1,
                'created_at' => date('Y-m-d H:i:s')
            ];

            // إضافة اسم المستخدم وكلمة المرور للمبرمج
            if ($data['role'] === 'مبرمج') {
                $insertData['username'] = Security::sanitizeInput($data['username']);
                $insertData['password'] = $data['password'];
            }

            // إضافة الرمز الشخصي للمشرف والمعلم
            if (in_array($data['role'], ['مشرف', 'معلم'])) {
                $insertData['personal_code'] = Security::sanitizeInput($data['personal_code'], 'int');
            }

            // إضافة الصلاحيات المخصصة إذا كانت موجودة
            if (!empty($data['custom_permissions'])) {
                $insertData['custom_permissions'] = json_encode($data['custom_permissions']);
            }

            // إدراج المستخدم
            $sql = "INSERT INTO {$this->table} (" . implode(', ', array_keys($insertData)) . ") VALUES (" . 
                   str_repeat('?,', count($insertData) - 1) . "?)";
            
            $userId = $this->db->insert($sql, array_values($insertData));

            Logger::info('User created successfully', [
                'user_id' => $userId,
                'role' => $data['role'],
                'created_by' => Auth::getInstance()->getCurrentUser()['id'] ?? null
            ]);

            return [
                'success' => true,
                'message' => 'تم إنشاء المستخدم بنجاح',
                'user_id' => $userId
            ];

        } catch (Exception $e) {
            Logger::error('User creation error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء المستخدم'
            ];
        }
    }

    /**
     * تحديث بيانات المستخدم
     */
    public function update($id, $data)
    {
        try {
            // التحقق من وجود المستخدم
            if (!$this->exists($id)) {
                return [
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ];
            }

            // التحقق من صحة البيانات
            $validation = $this->validateUserData($data, $id);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'بيانات غير صحيحة',
                    'errors' => $validation['errors']
                ];
            }

            // إعداد البيانات للتحديث
            $updateData = [];
            $updateFields = [];

            if (isset($data['full_name'])) {
                $updateData[] = Security::sanitizeInput($data['full_name']);
                $updateFields[] = 'full_name = ?';
            }

            if (isset($data['role'])) {
                $updateData[] = Security::sanitizeInput($data['role']);
                $updateFields[] = 'role = ?';
            }

            if (isset($data['username']) && !empty($data['username'])) {
                if ($this->usernameExists($data['username'], $id)) {
                    return [
                        'success' => false,
                        'message' => 'اسم المستخدم موجود مسبقاً'
                    ];
                }
                $updateData[] = Security::sanitizeInput($data['username']);
                $updateFields[] = 'username = ?';
            }

            if (isset($data['personal_code']) && !empty($data['personal_code'])) {
                if ($this->personalCodeExists($data['personal_code'], $id)) {
                    return [
                        'success' => false,
                        'message' => 'الرمز الشخصي موجود مسبقاً'
                    ];
                }
                $updateData[] = Security::sanitizeInput($data['personal_code'], 'int');
                $updateFields[] = 'personal_code = ?';
            }

            if (isset($data['password']) && !empty($data['password'])) {
                $updateData[] = Security::hashPassword($data['password']);
                $updateFields[] = 'password = ?';
                $updateFields[] = 'password_changed_at = NOW()';
            }

            if (isset($data['is_active'])) {
                $updateData[] = (int) $data['is_active'];
                $updateFields[] = 'is_active = ?';
            }

            if (isset($data['custom_permissions'])) {
                $updateData[] = json_encode($data['custom_permissions']);
                $updateFields[] = 'custom_permissions = ?';
            }

            if (empty($updateFields)) {
                return [
                    'success' => false,
                    'message' => 'لا توجد بيانات للتحديث'
                ];
            }

            // إضافة تاريخ التحديث
            $updateFields[] = 'updated_at = NOW()';
            $updateData[] = $id;

            // تنفيذ التحديث
            $sql = "UPDATE {$this->table} SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $affectedRows = $this->db->update($sql, $updateData);

            Logger::info('User updated successfully', [
                'user_id' => $id,
                'updated_by' => Auth::getInstance()->getCurrentUser()['id'] ?? null
            ]);

            return [
                'success' => true,
                'message' => 'تم تحديث بيانات المستخدم بنجاح',
                'affected_rows' => $affectedRows
            ];

        } catch (Exception $e) {
            Logger::error('User update error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث بيانات المستخدم'
            ];
        }
    }

    /**
     * حذف المستخدم (حذف منطقي)
     */
    public function delete($id)
    {
        try {
            if (!$this->exists($id)) {
                return [
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ];
            }

            // حذف منطقي
            $sql = "UPDATE {$this->table} SET is_active = 0, deleted_at = NOW() WHERE id = ?";
            $affectedRows = $this->db->update($sql, [$id]);

            Logger::info('User deleted successfully', [
                'user_id' => $id,
                'deleted_by' => Auth::getInstance()->getCurrentUser()['id'] ?? null
            ]);

            return [
                'success' => true,
                'message' => 'تم حذف المستخدم بنجاح',
                'affected_rows' => $affectedRows
            ];

        } catch (Exception $e) {
            Logger::error('User deletion error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف المستخدم'
            ];
        }
    }

    /**
     * الحصول على مستخدم بالمعرف
     */
    public function getById($id)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = ? AND is_active = 1";
            return $this->db->selectOne($sql, [$id]);
        } catch (Exception $e) {
            Logger::error('Get user by ID error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * الحصول على جميع المستخدمين
     */
    public function getAll($filters = [])
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE is_active = 1";
            $params = [];

            // تطبيق المرشحات
            if (!empty($filters['role'])) {
                $sql .= " AND role = ?";
                $params[] = $filters['role'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (full_name LIKE ? OR username LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $sql .= " ORDER BY created_at DESC";

            // تطبيق التصفح
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT ?";
                $params[] = (int) $filters['limit'];

                if (!empty($filters['offset'])) {
                    $sql .= " OFFSET ?";
                    $params[] = (int) $filters['offset'];
                }
            }

            return $this->db->select($sql, $params);

        } catch (Exception $e) {
            Logger::error('Get all users error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * عد المستخدمين
     */
    public function count($filters = [])
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE is_active = 1";
            $params = [];

            if (!empty($filters['role'])) {
                $sql .= " AND role = ?";
                $params[] = $filters['role'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (full_name LIKE ? OR username LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $result = $this->db->selectOne($sql, $params);
            return (int) $result['count'];

        } catch (Exception $e) {
            Logger::error('Count users error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * التحقق من وجود المستخدم
     */
    public function exists($id)
    {
        return $this->db->exists($this->table, ['id = ?', 'is_active = 1'], [$id]);
    }

    /**
     * التحقق من وجود اسم المستخدم
     */
    public function usernameExists($username, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE username = ? AND is_active = 1";
        $params = [$username];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->selectOne($sql, $params);
        return (int) $result['count'] > 0;
    }

    /**
     * التحقق من وجود الرمز الشخصي
     */
    public function personalCodeExists($personalCode, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE personal_code = ? AND is_active = 1";
        $params = [$personalCode];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->selectOne($sql, $params);
        return (int) $result['count'] > 0;
    }

    /**
     * التحقق من صحة بيانات المستخدم
     */
    private function validateUserData($data, $id = null)
    {
        $errors = [];

        // التحقق من الاسم الكامل
        if (empty($data['full_name'])) {
            $errors['full_name'] = 'الاسم الكامل مطلوب';
        } elseif (strlen($data['full_name']) < 2) {
            $errors['full_name'] = 'الاسم الكامل يجب أن يكون حرفين على الأقل';
        }

        // التحقق من الدور
        if (empty($data['role']) || !in_array($data['role'], ['مبرمج', 'مشرف', 'معلم'])) {
            $errors['role'] = 'يجب اختيار دور صحيح';
        }

        // التحقق من بيانات المبرمج
        if ($data['role'] === 'مبرمج') {
            if (empty($data['username'])) {
                $errors['username'] = 'اسم المستخدم مطلوب للمبرمج';
            } elseif (strlen($data['username']) < 3) {
                $errors['username'] = 'اسم المستخدم يجب أن يكون 3 أحرف على الأقل';
            }

            if (!$id && empty($data['password'])) {
                $errors['password'] = 'كلمة المرور مطلوبة للمبرمج الجديد';
            } elseif (!empty($data['password'])) {
                $strength = Security::checkPasswordStrength($data['password']);
                if ($strength['score'] < 3) {
                    $errors['password'] = 'كلمة المرور ضعيفة: ' . implode(', ', $strength['feedback']);
                }
            }
        }

        // التحقق من بيانات المشرف والمعلم
        if (in_array($data['role'], ['مشرف', 'معلم'])) {
            if (empty($data['personal_code'])) {
                $errors['personal_code'] = 'الرمز الشخصي مطلوب';
            } elseif (!preg_match('/^\d{4}$/', $data['personal_code'])) {
                $errors['personal_code'] = 'الرمز الشخصي يجب أن يكون 4 أرقام';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

