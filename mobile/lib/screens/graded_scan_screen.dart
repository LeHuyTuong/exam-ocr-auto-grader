import 'dart:async';
import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import '../models/graded_paper_result.dart';
import '../services/exam_service.dart';
import '../services/mlkit_service.dart';
import '../widgets/scan_overlay.dart' show ScanState;
import '../utils/error_utils.dart';
import 'graded_confirm_screen.dart';
import 'grade_list_screen.dart';

/// Grading flow for Unit Tests the teacher already marked by hand: capture
/// TWO tight crops in sequence — the pencil-written name line, then the
/// red-ink score strip — instead of one photo of the whole page. Small crops
/// read far more reliably than asking the AI to find the right handwriting
/// on a full page.
class GradedScanScreen extends StatefulWidget {
  final int classId;
  final String className;

  const GradedScanScreen({
    super.key,
    required this.classId,
    required this.className,
  });

  @override
  State<GradedScanScreen> createState() => _GradedScanScreenState();
}

enum _CropStep { name, scores }

class _GradedScanScreenState extends State<GradedScanScreen>
    with WidgetsBindingObserver {
  final _examService = ExamService();
  final _mlKitService = MLKitService();

  CameraController? _cameraController;
  List<CameraDescription>? _cameras;
  bool _cameraReady = false;
  CameraLensDirection _lensDirection = CameraLensDirection.back;

  ScanState _scanState = ScanState.idle;
  String? _lastFrameText;
  int _gradedCount = 0;
  bool _processing = false;

  _CropStep _step = _CropStep.name;
  GradedNameResult? _nameResult;

  DateTime _lastProcessTime = DateTime.now();
  static const Duration _processInterval = Duration(milliseconds: 800);
  bool _streaming = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initCamera();
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
            const SnackBar(
              content: Text('Cần cấp quyền Camera để quét bài thi.'),
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
        (c) => c.lensDirection == _lensDirection,
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

  Future<void> _toggleCamera() async {
    if (_cameras == null || _cameras!.length < 2) return;
    _stopCamera();
    setState(() {
      _cameraReady = false;
      _lensDirection = _lensDirection == CameraLensDirection.back
          ? CameraLensDirection.front
          : CameraLensDirection.back;
    });
    await _initCamera();
  }

  void _startImageStream() {
    if (_cameraController == null || _cameraController!.value.isStreamingImages) {
      return;
    }
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
        if (text.trim().isNotEmpty && text.length > 5) {
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

  Future<void> _manualCapture() async {
    if (_cameraReady && !_processing) {
      setState(() => _processing = true);
      await _captureAndProcess();
    }
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

      if (_step == _CropStep.name) {
        final result = await _examService.extractGradedName(
          image.path,
          widget.classId,
          mlkitHint,
        );
        if (!mounted) return;
        setState(() {
          _nameResult = result;
          _step = _CropStep.scores;
        });
        _resetAfterCapture();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Đã nhận tên: "${result.ocrRawName}" — giờ chụp dải điểm')),
          );
        }
        return;
      }

      final scoresResult = await _examService.extractGradedScores(
        image.path,
        widget.classId,
        mlkitHint,
      );

      if (!mounted) return;

      final nameResult = _nameResult!;
      _processing = false;
      _stopCamera();

      final saved = await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => GradedConfirmScreen(
            nameResult: nameResult,
            scoresResult: scoresResult,
            classId: widget.classId,
          ),
        ),
      );

      if (mounted) {
        setState(() {
          if (saved == true) _gradedCount++;
          _step = _CropStep.name;
          _nameResult = null;
        });
      }
      await _initCamera();
    } catch (e) {
      if (mounted) {
        _resetAfterCapture();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(friendlyError(e))),
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

  String get _instruction => _step == _CropStep.name
      ? 'Bước 1/2 — Chụp SÁT dòng tên học sinh (bút chì)'
      : 'Bước 2/2 — Chụp SÁT dải điểm cuối bài (mực đỏ)';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        title: Text('${widget.className} — Unit Test'),
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        actions: [
          if (_cameras != null && _cameras!.length > 1)
            IconButton(
              icon: const Icon(Icons.cameraswitch),
              tooltip: 'Đổi camera trước/sau',
              onPressed: _cameraReady ? _toggleCamera : null,
            ),
          IconButton(
            icon: const Icon(Icons.list_alt),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const GradeListScreen()),
              );
            },
          ),
        ],
      ),
      body: _cameraReady && _cameraController != null
          ? Stack(
              children: [
                CameraPreview(_cameraController!),
                _CropGuideOverlay(
                  state: _scanState,
                  instructionText: _instruction,
                  gradedCount: _gradedCount,
                ),
                Positioned(
                  bottom: 40,
                  left: 0,
                  right: 0,
                  child: Center(
                    child: _processing
                        ? const CircularProgressIndicator(color: Colors.white)
                        : FloatingActionButton(
                            onPressed: _manualCapture,
                            backgroundColor: Colors.white70,
                            tooltip: 'Chụp thủ công (không bắt buộc — máy tự chụp khi giữ yên)',
                            child: const Icon(Icons.camera_alt, color: Colors.black, size: 24),
                          ),
                  ),
                ),
              ],
            )
          : const Center(child: CircularProgressIndicator(color: Colors.white)),
    );
  }
}

/// A short, wide guide frame for cropping ONE line/strip of handwriting —
/// deliberately not the full-page A4 frame used elsewhere, since this flow
/// wants tight crops (a name line, then a score strip), not a whole page.
class _CropGuideOverlay extends StatelessWidget {
  final ScanState state;
  final String instructionText;
  final int gradedCount;

  const _CropGuideOverlay({
    required this.state,
    required this.instructionText,
    required this.gradedCount,
  });

  Color get _frameColor => switch (state) {
        ScanState.stable => Colors.green,
        ScanState.processing => Colors.orange,
        _ => Colors.white,
      };

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    final width = size.width * 0.85;
    final height = size.height * 0.16;

    return Stack(
      children: [
        Center(
          child: Container(
            width: width,
            height: height,
            decoration: BoxDecoration(
              border: Border.all(color: _frameColor, width: 2),
              borderRadius: BorderRadius.circular(12),
              color: Colors.black.withValues(alpha: 0.05),
            ),
            child: state == ScanState.processing
                ? const Center(child: CircularProgressIndicator(color: Colors.white))
                : null,
          ),
        ),
        Positioned(
          top: 60,
          left: 24,
          right: 24,
          child: Column(
            children: [
              Icon(
                state == ScanState.stable ? Icons.check_circle : Icons.camera_alt_outlined,
                color: _frameColor,
                size: 32,
              ),
              const SizedBox(height: 8),
              Text(
                instructionText,
                textAlign: TextAlign.center,
                style: TextStyle(color: _frameColor, fontSize: 16, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
        if (gradedCount > 0)
          Positioned(
            bottom: 100,
            left: 16,
            right: 16,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(
                color: Colors.black54,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.checklist, color: Colors.white, size: 20),
                  const SizedBox(width: 8),
                  Text(
                    'Đã chấm: $gradedCount bài',
                    style: const TextStyle(color: Colors.white, fontSize: 14),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}
