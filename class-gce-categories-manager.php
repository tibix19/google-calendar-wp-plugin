<?php
class GCE_Categories_Manager
{
    private $json_file;
    private $categories;

    public function __construct()
    {
        $this->json_file = plugin_dir_path(__FILE__) . 'categories.json';
        $this->load_categories();
    }

    private function load_categories()
    {
        if (file_exists($this->json_file)) {
            $json_content = file_get_contents($this->json_file);
            $data = json_decode($json_content, true);
            $this->categories = $data['categories'] ?? [];
        } else {
            $this->categories = [];
            $this->save_categories();
        }
    }

    private function save_categories()
    {
        $data = ['categories' => $this->categories];
        file_put_contents($this->json_file, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function get_categories()
    {
        return $this->categories;
    }

    public function add_category($name, $color)
    {
        $id = time(); // Utilisation du timestamp comme ID
        $slug = sanitize_title($name);

        $this->categories[] = [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'color' => $color
        ];

        $this->save_categories();
        return true;
    }

    public function delete_category($id)
    {
        foreach ($this->categories as $key => $category) {
            if ($category['id'] == $id) {
                unset($this->categories[$key]);
                $this->categories = array_values($this->categories); // Réindexer le tableau
                $this->save_categories();
                return true;
            }
        }
        return false;
    }
}
