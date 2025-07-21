<?php
// get_students_by_class.php
// Faili hili linapokea class_id kupitia ombi la AJAX na kurejesha orodha ya wanafunzi katika umbizo la JSON.

// Jumuisha faili ya muunganisho wa hifadhidata.
// HAKIKISHA NJIA HII NI SAHIHI KABISA.
// Kwa mfano, ikiwa 'get_students_by_class.php' iko katika saraka sawa na 'connection/db.php',
// basi 'connection/db.php' inatosha.
// Ikiwa 'get_students_by_class.php' iko ndani ya saraka ya 'admin/', na 'connection/db.php' iko nje yake,
// basi ingekuwa '.. /connection/db.php'.
include 'connection/db.php';

// Weka kichwa cha Content-Type kuwa 'application/json' ili kivinjari kijue kuwa inatarajiwa JSON.
header('Content-Type: application/json');

$students = []; // Anzisha safu tupu ya wanafunzi

// Angalia kama 'class_id' imetumwa kupitia GET na ni namba halali.
if (isset($_GET['class_id']) && is_numeric($_GET['class_id'])) {
    // Safisha class_id ili kuzuia mashambulizi ya sindano ya SQL na kuhakikisha ni int.
    $class_id = filter_var($_GET['class_id'], FILTER_VALIDATE_INT);

    // Ikiwa class_id ni namba halali baada ya kusafisha
    if ($class_id !== false) {
        // Andaa taarifa ya SQL ili kuchagua wanafunzi.
        // Tunatumia prepared statements kuzuia SQL injection.
        $stmt = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE class_id = ? ORDER BY first_name, last_name");

        // Angalia kama taarifa imeandaliwa kwa mafanikio.
        if ($stmt) {
            // Funga parameter kwa thamani (class_id). 'i' inawakilisha integer.
            $stmt->bind_param("i", $class_id);
            // Tekeleza taarifa.
            $stmt->execute();
            // Pata matokeo.
            $result = $stmt->get_result();

            // Pitia kila safu ya matokeo na uiongeze kwenye safu ya wanafunzi.
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            // Funga taarifa.
            $stmt->close();
        } else {
            // Hapa unaweza kuingiza ujumbe wa kosa kwenye log au kutuma kosa wazi zaidi.
            // Kwa madhumuni ya utatuzi, unaweza kuongeza `error_log("Failed to prepare statement: " . $conn->error);`
            // error_log("Failed to prepare statement in get_students_by_class.php: " . $conn->error);
        }
    } else {
        // Hapa unaweza kushughulikia kesi ambapo class_id iliyotumwa si namba halali.
        // error_log("Invalid class_id received in get_students_by_class.php: " . $_GET['class_id']);
    }
} else {
    // Hapa unaweza kushughulikia kesi ambapo class_id haikutumwa au haikutumwa ipasavyo.
    // error_log("class_id not set or not numeric in get_students_by_class.php.");
}

// Rudisha orodha ya wanafunzi (ambayo inaweza kuwa tupu) katika umbizo la JSON.
echo json_encode($students);

// Funga muunganisho wa hifadhidata.
$conn->close();
?>
