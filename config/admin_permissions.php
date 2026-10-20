<?php

return [
    'groups' => ['overview' => '数据概览', 'content' => '内容管理', 'ops' => '运营位', 'users' => '用户管理', 'commerce' => '商业化', 'ai' => 'AI 配置', 'tasks' => '激励任务', 'push' => '消息与推送', 'service' => '客服与反馈', 'system' => '系统设置'],
    'roles' => [
        'admin' => ['name' => '超级管理员', 'permissions' => array_fill_keys(['overview', 'content', 'ops', 'users', 'commerce', 'ai', 'tasks', 'push', 'service', 'system'], 'rw')],
        'ops' => ['name' => '运营', 'permissions' => ['overview' => 'r', 'content' => 'r', 'ops' => 'rw', 'tasks' => 'rw', 'push' => 'rw', 'service' => 'r']],
        'content' => ['name' => '内容', 'permissions' => ['overview' => 'r', 'content' => 'rw', 'ops' => 'r', 'service' => 'r']],
        'service' => ['name' => '客服', 'permissions' => ['users' => 'r', 'service' => 'rw', 'content' => 'r']],
        'finance' => ['name' => '财务', 'permissions' => ['overview' => 'r', 'commerce' => 'rw', 'users' => 'r']],
    ],
];
