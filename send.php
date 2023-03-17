<?php
    // Retrieve the values of the input fields from the form
    $username = $_POST['username'];
    $message = $_POST['message'];

    // TODO: Process the chat message, such as storing it in a database or sending it to a chat API

    // Redirect the user back to the chat page
    header("Location: index.html");
    exit();
?>