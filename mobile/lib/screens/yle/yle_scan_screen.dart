import 'dart:async';
import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import '../../models/yle_models.dart';
import '../../services/mlkit_service.dart';
import '../../services/yle_service.dart';
import '../../widgets/scan_overlay.dart';
import 'yle_student_confirm_screen.dart';
import 'yle_manual_grade_screen.dart';


class YleScanScreen extends StatefulWidget {
  final YleExam exam;
  final int classId;
  final String className;

  const YleScanScreen({
    super.key,
    required this.exam,
    required this.classId,
    required this.className,
  });

  @override
  State<YleScanScreen> createState() => _YleScanScreenState();
}

class _YleScanScreenState extends State<YleScanScreen> with WidgetsBindingObserver {
  final _yleService = YleService();
  final _mlKitService = MLKitService();

  CameraController? _cameraController;
  List<CameraDescription>? _cameras;
  bool _cameraReady = false;

  ScanState _scanState = ScanState.idle;
  String? _lastFrameText;
  bool _processing = false;

  DateTime _lastProcessTime = DateTime.now();
  static const _processInterval = Duration(milliseconds: 800);
  bool _streaming = false;

  // YLE-specific state
  YleSubmission? _submission;
  int _currentPage = 1;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initCamera();
    _createSubmission();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _stopCamera();
    _mlKitService.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _initCamera();
    } else if (state == AppLifecycleState.paused) {
      _stopCamera();
    }
  }

  Future<void> _createSubmission() async {
    try {
      final today = DateTime.now().toIso8601String().split('T').first;
      _submission = await _yleService.createSubmission(
        yleExamId: widget.exam.id,
        classId: widget.classId,
        examDate: today,
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Lỗi tạo submission: $e')),
        );
      }
    }
  }

  void _stopCamera() {
    _streaming = false;
    _cameraController?.stopImageStream();
    _cameraController?.dispose();
    _cameraController = null;
    _cameraReady = false;
  }

  Future<void> _initCamera() async {
    try {
      final status = await Permission.camera.request();
      if (!status.isGranted) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: const Text('Cần cấp quyền Camera để quét bài thi.'),
              action: SnackBarAction(
                label: 'Mở Cài đặt',
                onPressed: openAppSettings,
              ),
            ),
          );
        }
        return;
      }

      _cameras = await availableCameras();
      if (_cameras == null || _cameras!.isEmpty) return;
      final camera = _cameras!.firstWhere(
        (c) => c.lensDirection == CameraLensDirection.back,
        orElse: () => _cameras!.first,
      );

      _cameraController = CameraController(
        camera,
        ResolutionPreset.medium,
        enableAudio: false,
        imageFormatGroup: Platform.isAndroid
            ? ImageFormatGroup.nv21
            : ImageFormatGroup.bgra8888,
      );

      await _cameraController!.initialize();
      _startImageStream();

      if (mounted) setState(() => _cameraReady = true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Lỗi camera: $e')),
        );
      }
    }
  }

  void _startImageStream() {
    _streaming = true;
    _cameraController?.startImageStream(_processCameraImage);
  }

  void _processCameraImage(CameraImage image) {
    if (_processing || !_streaming) return;
    final now = DateTime.now();
    if (now.difference(_lastProcessTime) < _processInterval) return;
    _lastProcessTime = now;

    final camera = _cameraController?.description;
    if (camera == null) return;

    _mlKitService.recognizeCameraImage(image, camera).then((text) {
      if (!mounted || _processing || !_streaming) return;
      final stable = _mlKitService.isStableFrame(text, _lastFrameText);

      setState(() {
        if (text.trim().isNotEmpty && text.length > 10) {
          _scanState = stable ? ScanState.stable : ScanState.detecting;
          if (stable) _autoCapture();
        } else {
          _scanState = ScanState.idle;
        }
        _lastFrameText = text;
      });
    }).catchError((_) {});
  }

  void _autoCapture() {
    if (_processing) return;
    setState(() => _processing = true);
    _captureAndProcess();
  }

  Future<void> _captureAndProcess() async {
    try {
      _streaming = false;
      await _cameraController?.stopImageStream();

      final image = await _cameraController?.takePicture();
      if (image == null || !mounted) {
        _resetAfterCapture();
        return;
      }

      setState(() => _scanState = ScanState.processing);

      String? mlkitHint = _lastFrameText;
      try {
        final fileText = await _mlKitService.recognizeImage(image.path);
        if (fileText.trim().isNotEmpty) mlkitHint = fileText;
      } catch (_) {}

      if (_submission == null) {
        _resetAfterCapture();
        return;
      }

      final uploadResult = await _yleService.uploadPage(
        submissionId: _submission!.id,
        imagePath: image.path,
        pageNumber: _currentPage,
        mlkitHint: mlkitHint,
      );

      if (!mounted) {
        _resetAfterCapture();
        return;
      }

      // After page 1, show student confirmation with AI-extracted name and answers
      if (_currentPage == 1) {
        final candidates = (uploadResult['studentNameCandidates'] as List?)
            ?.map((c) => {
                  'studentId': c['studentId'] as int?,
                  'fullName': c['fullName'] as String? ?? '',
                  'similarity': (c['similarity'] as num?)?.toDouble() ?? 0.0,
                })
            .toList() ?? [];

        final answers = (uploadResult['answers'] as List?)
            ?.map((a) => YleAnswerItem.fromJson(a as Map<String, dynamic>))
            .toList() ?? [];

        final confirmed = await Navigator.push<bool>(
          context,
          MaterialPageRoute(
            builder: (_) => YleStudentConfirmScreen(
              submissionId: _submission!.id,
              classId: widget.classId,
              ocrRawName: _submission!.ocrRawName,
              candidates: candidates,
              answers: answers,
            ),
          ),
        );

        if (confirmed != true && mounted) {
          _resetAfterCapture();
          return;
        }

        // Refresh submission after student assignment
        _submission = await _yleService.getSubmissionResult(_submission!.id).then((r) => r.submission);
      }

      if (_currentPage < widget.exam.totalPages) {
        setState(() {
          _currentPage++;
          _processing = false;
          _scanState = ScanState.idle;
          _lastFrameText = null;
        });
        _startImageStream();

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Trang $_currentPage/${widget.exam.totalPages} — Tiếp tục quét')),
          );
        }
      } else {
        // All pages done — go to manual grading
        if (mounted) {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(
              builder: (_) => YleManualGradeScreen(
                exam: widget.exam,
                submissionId: _submission!.id,
                classId: widget.classId,
              ),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        _resetAfterCapture();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Lỗi: $e')),
        );
      }
    }
  }

  void _resetAfterCapture() {
    _processing = false;
    _scanState = ScanState.idle;
    _lastFrameText = null;
    if (_cameraController != null && _cameraController!.value.isInitialized) {
      _startImageStream();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        title: Text('${widget.exam.name} — Trang $_currentPage/${widget.exam.totalPages}'),
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
      ),
      body: _cameraReady && _cameraController != null
          ? Stack(
              children: [
                CameraPreview(_cameraController!),
                ScanOverlay(
                  state: _scanState,
                  gradedCount: _currentPage - 1,
                ),
                Positioned(
                  bottom: 40,
                  left: 0,
                  right: 0,
                  child: Center(
                    child: _processing
                        ? const CircularProgressIndicator(color: Colors.white)
                        : FloatingActionButton.large(
                            onPressed: _processing ? null : _captureAndProcess,
                            backgroundColor: Colors.white,
                            child: const Icon(Icons.camera_alt, color: Colors.black, size: 36),
                          ),
                  ),
                ),
                if (_submitting)
                  Container(
                    color: Colors.black54,
                    child: const Center(child: CircularProgressIndicator()),
                  ),
              ],
            )
          : const Center(child: CircularProgressIndicator(color: Colors.white)),
    );
  }
}
