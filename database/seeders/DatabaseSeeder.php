<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PermissionResource;
use App\Enums\PolicyAbility;
use App\Enums\PrintJobStatus;
use App\Enums\TableSessionStatus;
use App\Enums\UserRole;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\TableSession;
use App\Models\TableZone;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tạo bộ dữ liệu mẫu hoàn chỉnh cho môi trường phát triển.
 *
 * Dữ liệu được tạo theo đúng thứ tự phụ thuộc của khóa ngoại: cửa hàng và
 * người dùng trước, sau đó đến khu vực, bàn, thực đơn, phiên bàn, đơn hàng,
 * chi tiết đơn, thanh toán, máy in và cuối cùng là lệnh in.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Ghi dữ liệu mẫu cho toàn bộ mô hình nghiệp vụ của hệ thống POS.
     *
     * Seeder này tạo đúng 2 cửa hàng, 2 người dùng và 10 bản ghi cho mỗi mô
     * hình còn lại. Faker được cố định seed để dữ liệu có thể tái lập khi cần
     * kiểm tra giao diện hoặc luồng nghiệp vụ trong môi trường phát triển.
     */
    public function run(): void
    {
        // Cố định seed giúp cùng một lần chạy luôn sinh ra cùng kiểu dữ liệu,
        // từ đó việc kiểm thử và so sánh kết quả giữa các lần chạy dễ hơn.
        $faker = FakerFactory::create('vi_VN');
        $faker->seed(20260903);

        // Store là bảng gốc, vì vậy phải được tạo trước mọi bản ghi có
        // store_id. Hai cửa hàng đại diện cho hai chi nhánh POS độc lập.
        $stores = collect([
            [
                'name' => 'POS Nguyễn Huệ',
                'address' => '12 Nguyễn Huệ, Quận 1, Thành phố Hồ Chí Minh',
                'phone' => '02838220001',
                'email' => 'nguyenhue@example.com',
                'opening_hours' => '07:00 - 22:00',
                'is_active' => true,
            ],
            [
                'name' => 'POS Trần Phú',
                'address' => '88 Trần Phú, Hải Châu, Đà Nẵng',
                'phone' => '02363880002',
                'email' => 'tranphu@example.com',
                'opening_hours' => '08:00 - 23:00',
                'is_active' => true,
            ],
        ])->map(fn (array $attributes): Store => Store::create($attributes));

        // Xóa cache trước và sau khi khai báo quyền để Spatie không dùng dữ
        // liệu cũ trong cùng tiến trình seed hoặc khi ứng dụng đang chạy.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = collect(PermissionResource::cases())
            ->flatMap(fn (PermissionResource $resource): array => array_map(
                fn (PolicyAbility $ability): string => $resource->ability($ability),
                PolicyAbility::cases(),
            ));

        // Tạo toàn bộ permission theo cùng enum mà Model Policies sử dụng,
        // tránh sai tên quyền giữa seeder và lớp kiểm tra authorization.
        $permissionNames->each(fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'));

        // Spatie cache danh sách permission; cần xóa ngay sau khi tạo để bước
        // sync role bên dưới nhận đủ các quyền vừa được ghi vào cơ sở dữ liệu.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $ownerRole = Role::findOrCreate(UserRole::Owner->value, 'web');
        $staffRole = Role::findOrCreate(UserRole::Staff->value, 'web');

        // Owner được mọi quyền. Staff chỉ vận hành dữ liệu trong tenant và
        // không có quyền quản trị Store hoặc tài khoản người dùng.
        $ownerRole->syncPermissions($permissionNames);
        $staffRole->syncPermissions(
            collect(PermissionResource::cases())
                ->reject(fn (PermissionResource $resource): bool => in_array($resource, [PermissionResource::Store, PermissionResource::User], true))
                ->flatMap(fn (PermissionResource $resource): array => array_map(
                    fn (PolicyAbility $ability): string => $resource->ability($ability),
                    PolicyAbility::cases(),
                )),
        );

        // Owner không bị gắn cố định vào một Store và có thể chuyển giữa mọi
        // chi nhánh. Staff chỉ thuộc Store Trần Phú và không thấy Store khác.
        // Mật khẩu demo là "password", được model User tự băm bằng cast hashed.
        $owner = User::create([
            'store_id' => null,
            'name' => 'Chủ cửa hàng',
            'email' => 'owner@example.com',
            'email_verified_at' => now(),
            'password' => 'password',
        ]);
        $staff = User::create([
            'store_id' => $stores[1]->id,
            'name' => 'Nhân viên Trần Phú',
            'email' => 'staff.tranphu@example.com',
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $owner->assignRole($ownerRole);
        $staff->assignRole($staffRole);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Tạo 10 khu vực và phân bổ luân phiên cho hai cửa hàng để cả hai
        // chi nhánh đều có dữ liệu phục vụ việc kiểm tra bộ lọc theo store.
        $zones = collect(range(1, 10))->map(function (int $index) use ($stores, $faker): TableZone {
            return TableZone::create([
                'store_id' => $stores[($index - 1) % 2]->id,
                'name' => $faker->randomElement(['Trong nhà', 'Ngoài trời', 'Tầng 1', 'Tầng 2'])." {$index}",
                'is_active' => true,
            ]);
        });

        // Mỗi bàn dùng khu vực tương ứng; store_id được lấy từ khu vực để
        // bảo đảm bàn và khu vực luôn thuộc cùng một chi nhánh.
        $tables = collect(range(1, 10))->map(function (int $index) use ($zones): DiningTable {
            $zone = $zones[$index - 1];

            return DiningTable::create([
                'store_id' => $zone->store_id,
                'zone_id' => $zone->id,
                'name' => sprintf('Bàn %02d', $index),
            ]);
        });

        // Tạo 10 nhóm sản phẩm, mỗi nhóm thuộc một cửa hàng cụ thể để
        // kiểm tra quan hệ Store hasMany ProductGroup.
        $productGroups = collect(range(1, 10))->map(function (int $index) use ($stores, $faker): ProductGroup {
            return ProductGroup::create([
                'store_id' => $stores[($index - 1) % 2]->id,
                'name' => $faker->randomElement(['Cà phê', 'Trà', 'Nước ép', 'Đồ ăn'])." {$index}",
                'icon' => 'heroicon-o-cake',
                'sort_order' => $index,
                'is_active' => true,
            ]);
        });

        // Giá sản phẩm được tạo thành số nguyên tiền Việt để tránh dữ liệu
        // mẫu có phần thập phân khó đọc, nhưng vẫn lưu đúng kiểu DECIMAL.
        $products = collect(range(1, 10))->map(function (int $index) use ($productGroups, $faker): Product {
            return Product::create([
                'store_id' => $productGroups[$index - 1]->store_id,
                'product_group_id' => $productGroups[$index - 1]->id,
                'name' => $faker->randomElement(['Cà phê sữa', 'Trà đào', 'Nước cam', 'Bánh mì'])." {$index}",
                'description' => 'Sản phẩm mẫu dùng cho môi trường phát triển.',
                'price' => $faker->numberBetween(25000, 95000),
                'is_active' => true,
                'is_sku' => false,
            ]);
        });

        // Tạo các phiên đã đóng để có thể liên kết với đơn hàng đã thanh
        // toán, đồng thời vẫn mô phỏng đầy đủ vòng đời của một phiên bàn.
        $sessions = collect(range(1, 10))->map(function (int $index) use ($tables): TableSession {
            $startTime = now()->subDays(10 - $index)->setTime(18, 0);

            return TableSession::create([
                'store_id' => $tables[$index - 1]->store_id,
                'table_id' => $tables[$index - 1]->id,
                'start_time' => $startTime,
                'end_time' => $startTime->copy()->addHours(2),
                'status' => TableSessionStatus::Closed,
            ]);
        });

        // Mỗi phiên có một đơn hàng đã thanh toán. Tổng tiền ban đầu được
        // tính từ đúng một sản phẩm tương ứng, sau đó OrderItem lưu snapshot
        // đơn giá tại thời điểm gọi món.
        $orders = collect(range(1, 10))->map(function (int $index) use ($sessions, $products, $faker): Order {
            $product = $products[$index - 1];
            // Dùng công thức cố định để Order và OrderItem luôn có cùng số
            // lượng, nhờ đó tổng tiền mẫu luôn khớp với chi tiết đơn hàng.
            $quantity = (($index - 1) % 3) + 1;

            return Order::forceCreate([
                'store_id' => $sessions[$index - 1]->store_id,
                'table_session_id' => $sessions[$index - 1]->id,
                // Seeder tắt model events, nên mã mẫu phải được gán trực tiếp.
                'code' => 'ORD-'.now()->format('mdy').'-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'status' => OrderStatus::Paid,
                'total' => $product->price * $quantity,
                'notes' => $faker->optional()->sentence(),
            ]);
        });

        // Seeder tắt model events, nên đồng bộ sequence với số mã đã gán trực tiếp.
        $orders->groupBy('store_id')->each(function ($storeOrders, $storeId): void {
            DB::table('order_sequences')->updateOrInsert(
                ['store_id' => $storeId, 'sequence_date' => today()->toDateString()],
                ['last_number' => $storeOrders->count(), 'updated_at' => now(), 'created_at' => now()],
            );
        });

        // Mỗi đơn có một dòng chi tiết, giữ nguyên giá sản phẩm tại thời
        // điểm order để minh họa đúng ý nghĩa của cột unit_price snapshot.
        collect(range(1, 10))->each(function (int $index) use ($orders, $products, $faker): void {
            $product = $products[$index - 1];

            OrderItem::forceCreate([
                'store_id' => $orders[$index - 1]->store_id,
                'order_id' => $orders[$index - 1]->id,
                'product_id' => $product->id,
                'quantity' => (($index - 1) % 3) + 1,
                'unit_price' => $product->price,
                'notes' => $faker->optional()->randomElement(['Ít đá', 'Không đường', 'Thêm topping']),
            ]);
        });

        // Tạo một thanh toán hoàn tất cho mỗi đơn. Số tiền lấy từ tổng đơn
        // để dữ liệu đối soát giữa Order và Payment nhất quán.
        collect(range(1, 10))->each(function (int $index) use ($orders): void {
            Payment::forceCreate([
                'store_id' => $orders[$index - 1]->store_id,
                'order_id' => $orders[$index - 1]->id,
                'amount' => $orders[$index - 1]->total,
                'payment_method' => PaymentMethod::Cash,
                'status' => PaymentStatus::Completed,
                'paid_at' => now()->subDays(10 - $index)->setTime(20, 0),
            ]);
        });

        // Mỗi chi nhánh có nhiều loại máy in để kiểm tra cấu hình máy in
        // theo cửa hàng và các loại receipt, kitchen, label.
        $printers = collect(range(1, 10))->map(function (int $index) use ($stores): Printer {
            return Printer::create([
                'store_id' => $stores[($index - 1) % 2]->id,
                'name' => 'Máy in '.$index,
                'printer_type' => ['receipt', 'kitchen', 'label'][($index - 1) % 3],
                'ip_address' => "192.168.1.{$index}",
                'port' => 9100,
                'is_active' => true,
            ]);
        });

        // Lệnh in liên kết đồng thời tới máy in và đơn hàng, payload lưu
        // snapshot tối giản để minh họa dữ liệu JSON dùng khi in lại.
        collect(range(1, 10))->each(function (int $index) use ($printers, $orders, $products): void {
            PrintJob::create([
                'store_id' => $printers[$index - 1]->store_id,
                'printer_id' => $printers[$index - 1]->id,
                'order_id' => $orders[$index - 1]->id,
                'print_type' => $printers[$index - 1]->printer_type->value,
                'status' => PrintJobStatus::Printed,
                'attempts' => 1,
                'payload' => [
                    'order_id' => $orders[$index - 1]->id,
                    'product_name' => $products[$index - 1]->name,
                    'total' => $orders[$index - 1]->total,
                ],
                'printed_at' => now()->subDays(10 - $index)->setTime(20, 1),
            ]);
        });
    }
}
