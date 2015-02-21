<?php

/** Gallery images list controller action */
function gallery_list($sorter = null, $direction = 'ASC', $current_page = null, $page_size=4)
{
    // If no sorter is passed
    if (!isset($sorter)) {
        // Load sorter from session if it is there
        $sorter = isset($_SESSION['sorter']) ? $_SESSION['sorter'] : null;
        $direction = isset($_SESSION['direction']) ? $_SESSION['direction'] : null;
    }

    if (!isset($current_page)) {
        // Load current page from session if it is there
        $current_page = isset($_SESSION['current_page']) ? $_SESSION['current_page'] : 1;
    }

    // Rendered HTML gallery items
    $items = '';

    // Prepare db query object
    $query = dbQuery('gallery');


    // Set the prefix for pager
    $url_prefix = "gallery/list/".$sorter."/".$direction."/";
    // Count the number of images in query
    $rows_count = $query->count();

    if (isset($current_page)) {
        // If we don't want to show all images
        if ($current_page != 0) {
            // Set the limit condition to db request
            $query->limit(($current_page - 1) * $page_size, $page_size);
            // Create a new instance of Pager
            $pager = new \samson\pager\Pager($current_page, $page_size, $url_prefix, $rows_count);
        } else {
            // Set the page size to leave Pager in the same condition
            $page_size = 4;
            $pager = new \samson\pager\Pager($current_page, $page_size, $url_prefix, $rows_count);
        }
        // Store current psge in a session
        $_SESSION['current_page'] = $current_page;
        // Create the output of Pager
        $pages = $pager->toHtml();
    }

    // If sorter is passed
    if (isset($sorter) && in_array($sorter, array('Loaded', 'size'))) {
        // Add sorting condition to db request
        $query->order_by($sorter, $direction);

        // Store sorting in a session
        $_SESSION['sorter'] = $sorter;
        $_SESSION['direction'] = $direction;
    }

    // Iterate all records from "gallery" table
    foreach ($query->exec() as $dbItem) {
        /**@var \samson\activerecord\gallery $dbItem``` */

        /*
         *   Render view(output method) and pass object received fron DB and
         * prefix all its fields with "image_", return and gather this outputs
         * in $items
         */
        $items .= m()->view('gallery/item')->image($dbItem)->output();
    }

    /* Set window title and view to render, pass items variable to view, pass pager variable to view*/
    m()->view('gallery/index')->title('My gallery')->items($items)->pager($pages);
}

/** Gallery universal controller */
function gallery__HANDLER()
{
    // Call our lsit controller
    gallery_list();
}

/**
 * Gallery form controller action
 * @var string $id Item identifier
 */
function gallery_form($id = null)
{
    /*@var \samson\activerecord\gallery $dbItem */
    $dbItem = null;
    /*
     * Try to recieve one first record from DB by identifier,
     * if $id == null the request will fail anyway, and in case
     * of success store record into $dbItem variable
     */

    if (dbQuery('gallery')->id($id)->first($dbItem)) {
        // Render the form to redact item
        m()->view('gallery/form/index')->title('Redact form')->image($dbItem);
    } elseif (isset($id)) {
        // File with passed ID wasn't find in DB
        m()->view('gallery/form/notfoundID')->title('Not Found');
    } else {
        // No ID was passed
        m()->view('gallery/form/newfile')->title('New Photo');
    }
}

/**
 * Gallery form controller action
 * @var string $id Item identifier
 */
function gallery_save()
{
    // If we have really received form data
    if (isset($_POST)) {

        /** @var \samson\activerecord\gallery $dbItem */
        $dbItem = null;

        // Clear received variable
        $id = isset($_POST['id']) ? filter_var($_POST['id']) : null;

        /*
         * Try to receive one first record from DB by identifier,
         * in case of success store record into $dbItem variable,
         * otherwise create new gallery item
         */
        if (!dbQuery('gallery')->id($id)->first($dbItem)) {
            // Create new instance but without creating a db record
            $dbItem = new \samson\activerecord\gallery(false);
        }

        // Save image name
        $dbItem->Name = filter_var($_POST['name']);
        $dbItem->save();

        // At this point we can guarantee that $dbItem is not empty
        if (isset($_FILES['file']['tmp_name']) && $_FILES['file']['tmp_name'] != null) {
            $tmp_name = $_FILES["file"]["tmp_name"];
            $name = $_FILES["file"]["name"];

            // Create upload dir with correct rights
            if (!file_exists('upload')) {
                mkdir('upload', 0775);
            }

            $src = 'upload/' . md5(time() . $name);

            // If file has been created
            if (move_uploaded_file($tmp_name, $src)) {
                // Store file in upload dir
                $dbItem->Src = $src;
                $dbItem->size = $_FILES["file"]["size"];
                $dbItem->Name = $name;
                // Save image
                $dbItem->save();
            }

        }

    }

    // Redirect to main page
    url()->redirect();
}

/**
 * Delete controller action
 *@var string $id Item db identifier
 */
function gallery_delete($id)
{
    /** @var \samson\activerecord\gallery $dbItem */
    $dbItem = null;
    if (dbQuery('gallery')->id($id)->first($dbItem)) {
        // Delete uploaded file
        unlink($dbItem->Src);
        // Delete DB record about this file
        $dbItem->delete();
    }

    // Go to main page
    url()->redirect();
}
