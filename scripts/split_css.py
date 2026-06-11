import os

with open('public/assets/css/global.css', 'r') as f:
    lines = f.readlines()

parts = {
    'base.css': (1, 996),
    'ui.css': (997, 1185),
    'dashboard.css': (1186, 1514),
    'storefront.css': (1515, 1672)
}

os.makedirs('public/assets/css/modules', exist_ok=True)
imports = []

for name, (start, end) in parts.items():
    slice_lines = lines[start - 1:end]
    with open(f'public/assets/css/modules/{name}', 'w') as f:
        f.writelines(slice_lines)
    imports.append(f"@import url('./modules/{name}');\n")

with open('public/assets/css/global.css', 'w') as f:
    f.writelines(imports)

print("Split global.css successfully")
