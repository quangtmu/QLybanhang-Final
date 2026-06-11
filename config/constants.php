<?php

declare(strict_types=1);

const APP_NAME = 'He thong Quan ly Ban hang';
const APP_VERSION = '1.0.0';

const USER_TYPE_ADMIN = 'admin';
const USER_TYPE_SUB_ADMIN_ACTIVE = 'sub_admin_active';
const USER_TYPE_SUB_ADMIN_INACTIVE = 'sub_admin_inactive';
const USER_TYPE_STORE_PENDING = 'store_pending';
const USER_TYPE_STORE_APPROVED = 'store_approved';
const USER_TYPE_STORE_REJECTED = 'store_rejected';
const USER_TYPE_STORE_SUSPENDED = 'store_suspended';
const USER_TYPE_STORE_EMPLOYEE = 'store_employee';
const USER_TYPE_USER = 'user';
const USER_TYPE_USER_BANNED = 'user_banned';

const USER_TYPES = [
    USER_TYPE_ADMIN,
    USER_TYPE_SUB_ADMIN_ACTIVE,
    USER_TYPE_SUB_ADMIN_INACTIVE,
    USER_TYPE_STORE_PENDING,
    USER_TYPE_STORE_APPROVED,
    USER_TYPE_STORE_REJECTED,
    USER_TYPE_STORE_SUSPENDED,
    USER_TYPE_STORE_EMPLOYEE,
    USER_TYPE_USER,
    USER_TYPE_USER_BANNED,
];

const PRODUCT_STATUS_DRAFT = 'draft';
const PRODUCT_STATUS_PENDING_REVIEW = 'pending_review';
const PRODUCT_STATUS_APPROVED = 'approved';
const PRODUCT_STATUS_REJECTED = 'rejected';
const PRODUCT_STATUS_ARCHIVED = 'archived';

const ORDER_STATUS_PENDING = 'pending';
const ORDER_STATUS_CONFIRMED = 'confirmed';
const ORDER_STATUS_PROCESSING = 'processing';
const ORDER_STATUS_SHIPPED = 'shipped';
const ORDER_STATUS_DELIVERING = 'delivering';
const ORDER_STATUS_DELIVERED = 'delivered';
const ORDER_STATUS_CANCELLED = 'cancelled';
const ORDER_STATUS_REFUNDING = 'refunding';
const ORDER_STATUS_REFUNDED = 'refunded';

const SHIPMENT_STATUS_WAITING_PICKUP = 'waiting_pickup';
const SHIPMENT_STATUS_PICKED_UP = 'picked_up';
const SHIPMENT_STATUS_IN_TRANSIT = 'in_transit';
const SHIPMENT_STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
const SHIPMENT_STATUS_DELIVERED = 'delivered';
const SHIPMENT_STATUS_CANCELLED = 'cancelled';

const CATEGORY_LEVEL_LARGE = 'large';
const CATEGORY_LEVEL_MEDIUM = 'medium';
const CATEGORY_LEVEL_SMALL = 'small';

const MODULE_ADMIN_DASHBOARD = 'admin_dashboard';
const MODULE_USERS = 'users';
const MODULE_STORES = 'stores';
const MODULE_PRODUCTS = 'products';
const MODULE_CATEGORIES = 'categories';
const MODULE_TAGS = 'tags';
const MODULE_BANNERS = 'banners';
const MODULE_ORDERS = 'orders';
const MODULE_SHIPMENTS = 'shipments';
const MODULE_INVOICES = 'invoices';
const MODULE_CHAT = 'chat';
const MODULE_STORE_EMPLOYEES = 'store_employees';
const MODULE_CONFIGS = 'configs';

const DEFAULT_PAGE_SIZE = 20;
const MAX_PAGE_SIZE = 100;
const PASSWORD_MIN_LENGTH = 8;

const UPLOAD_DIR = BASE_PATH . '/uploads';
const EXPORT_DIR = BASE_PATH . '/exports';

const STORAGE_PROVIDER = 'local';
const STORAGE_LOCAL_PUBLIC_DIR = PUBLIC_PATH . '/uploads';
const STORAGE_PUBLIC_URL = '/uploads';
