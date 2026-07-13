import 'dart:io';
import 'dart:ui';
import 'package:camera/camera.dart';
import 'package:google_mlkit_text_recognition/google_mlkit_text_recognition.dart';

class MLKitService {
  final TextRecognizer _recognizer =
      TextRecognizer(script: TextRecognitionScript.latin);

  void dispose() {
    _recognizer.close();
  }

  Future<String> recognizeImage(String imagePath) async {
    final inputImage = InputImage.fromFile(File(imagePath));
    final recognisedText = await _recognizer.processImage(inputImage);
    return recognisedText.text;
  }

  Future<String> recognizeCameraImage(
      CameraImage image, CameraDescription camera) async {
    try {
      final inputImage = _inputImageFromCamera(image, camera);
      if (inputImage == null) return '';
      final recognisedText = await _recognizer.processImage(inputImage);
      return recognisedText.text;
    } catch (_) {
      return '';
    }
  }

  bool isStableFrame(String currentText, String? previousText,
      {int minLength = 15, double similarityThreshold = 0.8}) {
    if (currentText.trim().length < minLength) return false;
    if (previousText == null || previousText.isEmpty) return false;
    final similarity = _textSimilarity(currentText, previousText);
    return similarity >= similarityThreshold;
  }

  double _textSimilarity(String a, String b) {
    if (a.isEmpty && b.isEmpty) return 1.0;
    if (a.isEmpty || b.isEmpty) return 0.0;
    final aWords = a.split(RegExp(r'\s+'));
    final bWords = b.split(RegExp(r'\s+'));
    if (aWords.isEmpty || bWords.isEmpty) return 0.0;
    final common = aWords.where((w) => bWords.contains(w)).length;
    return common / (aWords.length + bWords.length - common).toDouble();
  }

  InputImage? _inputImageFromCamera(
      CameraImage image, CameraDescription camera) {
    final rotation = _rotationFromSensor(camera.sensorOrientation);
    if (rotation == null) return null;

    final format = image.format.group == ImageFormatGroup.nv21
        ? InputImageFormat.nv21
        : image.format.group == ImageFormatGroup.bgra8888
            ? InputImageFormat.bgra8888
            : InputImageFormat.nv21;

    final plane = image.planes.first;

    return InputImage.fromBytes(
      bytes: plane.bytes,
      metadata: InputImageMetadata(
        size: Size(image.width.toDouble(), image.height.toDouble()),
        rotation: rotation,
        format: format,
        bytesPerRow: plane.bytesPerRow,
      ),
    );
  }

  InputImageRotation? _rotationFromSensor(int sensorOrientation) {
    switch (sensorOrientation) {
      case 90:
        return InputImageRotation.rotation90deg;
      case 180:
        return InputImageRotation.rotation180deg;
      case 270:
        return InputImageRotation.rotation270deg;
      default:
        return InputImageRotation.rotation0deg;
    }
  }
}
