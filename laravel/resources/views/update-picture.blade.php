<!DOCTYPE html>
<html>
<head>
    <title>Upload Profile Picture</title>
</head>
<body>
    <h1>Upload Profile Picture</h1>

    <form action="{{ route('users.profile.picture.update') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <label for="user_id">User ID:</label>
        <input type="number" name="user_id" required>
        <br><br>

        <label for="profile_picture">Choose picture:</label>
        <input type="file" name="profile_picture" accept="image/*" required>
        <br><br>

        <button type="submit">Upload</button>
    </form>
</body>
</html>
